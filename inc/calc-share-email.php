<?php
/**
 * 257 Calculators — shared "email this calculation" backend.
 * ============================================================
 * One REST endpoint serves every calculator block's share row:
 *
 *   POST /wp-json/two57/v1/calc-share-email
 *   Body (JSON) {
 *     "calc":     "hours-to-impact" | "workspace-pricing" | ...,
 *     "email":    "you@example.com",
 *     "consent":  true,                 // must be === true
 *     "website":  "",                   // honeypot — non-empty = bot
 *     "page":     "/calculator/hours-to-impact/",  // pathname of the page
 *     "state":    { "team": 2, "days": 5, "weeks": 46, "hours": 8 }
 *   }
 *
 * Server flow:
 *   1. Honeypot filled → fake success, do nothing.
 *   2. is_email() validation.
 *   3. Consent === true gate.
 *   4. Per-calc state sanitisation + bound-check.
 *   5. Server-side recompute from ACF Options (authoritative figures).
 *   6. Compose + send email (MailPoet MailerFactory → wp_mail fallback).
 *   7. Captured as a MailPoet lead on the shared "Calculator leads" list,
 *      stamped with a calc_source custom field (§6.4 of the plan).
 *   8. Per-IP rate limit: 3 submits / 10 min (transients).
 *
 * @see docs/wp-calculators-plan.md §6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the REST route.
 */
add_action( 'rest_api_init', function (): void {
	register_rest_route( 'two57/v1', '/calc-share-email', [
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => '__return_true',
		'callback'            => 'two57_calc_share_email_handle',
	] );
} );


/**
 * Handle POST /wp-json/two57/v1/calc-share-email.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function two57_calc_share_email_handle( WP_REST_Request $request ): WP_REST_Response {
	$params = (array) $request->get_json_params();
	if ( empty( $params ) ) {
		$params = (array) $request->get_params();
	}

	// 1. Honeypot — bots fill the visually-hidden "website" field.
	if ( ! empty( $params['website'] ) ) {
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	// Rate limit (checked before any expensive work).
	$limited = two57_calc_share_rate_limited();
	if ( is_wp_error( $limited ) ) {
		return new WP_REST_Response( [ 'success' => false, 'message' => $limited->get_error_message() ], 429 );
	}

	$calc = sanitize_key( $params['calc'] ?? '' );

	// 2. Email validation.
	$email = sanitize_email( $params['email'] ?? '' );
	if ( ! is_email( $email ) ) {
		return new WP_REST_Response( [ 'success' => false, 'message' => 'Please enter a valid email address.' ], 400 );
	}

	// 3. Consent gate — the "contact policy" contract.
	if ( empty( $params['consent'] ) || true !== $params['consent'] ) {
		return new WP_REST_Response( [ 'success' => false, 'message' => 'You must accept the contact policy to send.' ], 400 );
	}

	// 4. Sanitise + bound-check the state for this calc.
	$state = isset( $params['state'] ) && is_array( $params['state'] ) ? $params['state'] : [];
	$state = two57_calc_sanitize_state( $calc, $state );
	if ( is_wp_error( $state ) ) {
		return two57_calc_share_respond( $state );
	}

	// 5. Recompute authoritatively from ACF Options.
	switch ( $calc ) {
		case 'hours-to-impact':
			$figures = two57_calc_figures_hours_to_impact( $state );
			break;
		case 'workspace-pricing':
			$figures = two57_calc_figures_workspace_pricing( $state );
			break;
		case 'meet-pricing':
			$figures = two57_calc_figures_meet_pricing( $state );
			break;
		default:
			$figures = new WP_Error( 'unsupported_calc', 'Unsupported calculator.' );
	}
	if ( is_wp_error( $figures ) || ! is_array( $figures ) ) {
		return two57_calc_share_respond( $figures instanceof WP_Error ? $figures : new WP_Error( 'unsupported_calc', 'Unsupported calculator.' ) );
	}

	// 6. Compose + send the email.
	$page = sanitize_text_field( $params['page'] ?? '/' );
	$email_body = two57_calc_compose_email( $calc, $figures, $state, $page, $email );
	$sent       = two57_calc_share_send_email( $email, $email_body );

	if ( is_wp_error( $sent ) ) {
		// Do not expose low-level mailer errors; log and return a generic failure.
		error_log( '[two57 calc-share-email] send failed: ' . $sent->get_error_message() );
		return new WP_REST_Response( [ 'success' => false, 'message' => "We couldn't send that just now, please try again in a few minutes." ], 500 );
	}

	// 7. Captured as a lead regardless — consented, real, transactional.
	two57_calc_capture_lead( $email, $calc );

	return new WP_REST_Response( [ 'success' => true ], 200 );
}


/**
 * Per-IP rate limit — max 3 calc-share-email submits per 10 minutes.
 *
 * @return true|WP_Error
 */
function two57_calc_share_rate_limited() {
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : '';
	$key  = 'two57_calc_share_rl_' . md5( $ip . $salt );
	$count = (int) get_transient( $key );

	if ( $count >= 3 ) {
		return new WP_Error( 'rate_limited', 'Try again in a few minutes.' );
	}

	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	return true;
}


/**
 * Normalise + bound-check a calc state array. Each calculator defines its
 * accepted keys and ranges; anything outside the engine's bounds is clamped.
 *
 * @param string $calc
 * @param array  $state
 * @return array|WP_Error
 */
function two57_calc_sanitize_state( string $calc, array $state ) {
	switch ( $calc ) {
		case 'hours-to-impact':
			return [
				'team'         => two57_calc_int( $state['team'] ?? 1, 1, 30 ),
				'daysPerWeek'  => two57_calc_int( $state['days'] ?? $state['daysPerWeek'] ?? 5, 1, 5 ),
				'weeksPerYear' => two57_calc_int( $state['weeks'] ?? $state['weeksPerYear'] ?? 46, 1, 52 ),
				'hoursPerDay'  => two57_calc_float( $state['hours'] ?? $state['hoursPerDay'] ?? 8, 1, 24 ),
			];
		case 'workspace-pricing':
			// Clamp team min 1: a zero-team "$0" email is meaningless (§C1 plan).
			// Commitment snaps to the nearest valid term {1,3,5}.
			$commitment = two57_calc_int( $state['commitment'] ?? 1, 1, 5 );
			if ( ! in_array( $commitment, [ 1, 3, 5 ], true ) ) {
				$commitment = ( $commitment <= 2 ) ? 1 : ( ( $commitment <= 4 ) ? 3 : 5 );
			}
			return [
				'team'       => two57_calc_int( $state['team'] ?? 1, 1, 15 ),
				'commitment' => $commitment,
				'annual'     => ! empty( $state['annual'] ),
				'members'    => two57_calc_sanitize_members( $state['members'] ?? [] ),
			];
		case 'meet-pricing':
			return two57_calc_sanitize_meet_pricing( $state );
	}

	return new WP_Error( 'unsupported_calc', 'Unsupported calculator.' );
}


/**
 * Sanitise the per-member roster for workspace-pricing. Each member is a
 * single tier slug; unknown or empty tiers contribute nothing (mirrors the
 * engine's unselected-member behaviour).
 *
 * @param array $members Raw roster.
 * @return array List of tier slugs, trimmed to the allowed set.
 */
function two57_calc_sanitize_members( array $members ): array {
	$allowed   = [ 'dedicated', 'flexi-5', 'flexi-4', 'flexi-3', 'flexi-2', 'flexi-1' ];
	$sanitized = [];
	foreach ( $members as $member ) {
		$tier = is_array( $member ) ? sanitize_key( $member['tier'] ?? '' ) : sanitize_key( (string) $member );
		if ( $tier === '' ) {
			$sanitized[] = '';
			continue;
		}
		if ( in_array( $tier, $allowed, true ) ) {
			$sanitized[] = $tier;
		}
	}
	return $sanitized;
}


/**
 * C2 — meet-pricing state sanitisation. Normalises + bound-checks the
 * quote state the JS engine sends on email-submit. Each addon flag coerces
 * to bool; people is clamped 1-200 (engine scale); per-day date/time fields
 * are validated as ISO date + HH:MM time strings. Anything out of bounds
 * is clamped (matches the engine's Math.max/Math.min behaviour).
 *
 * @param array $state raw { people, room?, duration?, days[], addons{} }
 * @return array sanitized state shape — never WP_Error (meets the contract).
 */
function two57_calc_sanitize_meet_pricing( array $state ): array {
	$rooms_allowed    = array_keys( two57_meet_rooms() );
	$durations_allowed = [ 'hour', 'day', 'evening' ];

	$people = two57_calc_int( $state['people'] ?? 6, 1, 200 );

	$room = is_string( $state['room'] ?? null ) ? sanitize_key( $state['room'] ) : '';
	if ( ! in_array( $room, $rooms_allowed, true ) ) {
		$room = '';
	}

	$duration = is_string( $state['duration'] ?? null ) ? sanitize_key( $state['duration'] ) : '';
	if ( ! in_array( $duration, $durations_allowed, true ) ) {
		$duration = '';
	}

	// Per-day: validate date (YYYY-MM-DD), start/end (HH:MM). Cap days at 14 (a
	// fortnight — the engine doesn't enforce a max but sending a 100-line
	// quote summary in an email is meaningless).
	$raw_days = is_array( $state['days'] ?? null ) ? $state['days'] : [];
	if ( ! $raw_days ) {
		$raw_days = [ [ 'date' => gmdate( 'Y-m-d' ), 'start' => '09:00', 'end' => '17:00' ] ];
	}
	$days = [];
	foreach ( $raw_days as $day ) {
		if ( ! is_array( $day ) ) {
			continue;
		}
		$date = sanitize_text_field( (string) ( $day['date'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}
		$start = sanitize_text_field( (string) ( $day['start'] ?? '09:00' ) );
		$end   = sanitize_text_field( (string) ( $day['end'] ?? '17:00' ) );
		if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $start ) ) $start = '09:00';
		if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $end ) )   $end   = '17:00';
		$days[] = [ 'date' => $date, 'start' => $start, 'end' => $end ];
		if ( count( $days ) >= 14 ) break; // a fortnight max
	}
	if ( ! $days ) {
		$days = [ [ 'date' => gmdate( 'Y-m-d' ), 'start' => '09:00', 'end' => '17:00' ] ];
	}

	// Addon state — coerce each to a strict bool, clamp the numeric knobs.
	$raw_addons = is_array( $state['addons'] ?? null ) ? $state['addons'] : [];
	$addons = [
		'tea'             => ! empty( $raw_addons['tea'] ),
		'teaType'         => isset( $raw_addons['teaType'] ) ? two57_calc_float( $raw_addons['teaType'], 0, 100 ) : 5,
		'catering'        => ! empty( $raw_addons['catering'] ),
		'cateringPerHead' => isset( $raw_addons['cateringPerHead'] ) ? two57_calc_float( $raw_addons['cateringPerHead'], 0, 200 ) : 25,
		'projector'        => ! empty( $raw_addons['projector'] ),
		'sound'           => ! empty( $raw_addons['sound'] ),
		'impact'          => ! empty( $raw_addons['impact'] ) || ! empty( $state['impact'] ),
	];

	return [
		'people'   => $people,
		'room'     => $room,
		'duration' => $duration,
		'days'     => $days,
		'addons'   => $addons,
		'impact'   => $addons['impact'],
	];
}


/**
 * Clamp + parse an integer from a raw request value.
 *
 * @param mixed $value
 * @param int   $min
 * @param int   $max
 * @return int
 */
function two57_calc_int( $value, int $min, int $max ): int {
	return max( $min, min( $max, (int) $value ) );
}


/**
 * Clamp + parse a float from a raw request value.
 *
 * @param mixed $value
 * @param float $min
 * @param float $max
 * @return float
 */
function two57_calc_float( $value, float $min, float $max ): float {
	return max( $min, min( $max, (float) $value ) );
}


/**
 * C6 — hours-to-impact recompute. Reads the giving rate from ACF Options
 * (the SSOT), never from the client.
 *
 * @param array $state sanitized { team, daysPerWeek, weeksPerYear, hoursPerDay }
 * @return array|WP_Error
 */
function two57_calc_figures_hours_to_impact( array $state ) {
	if ( ! function_exists( 'get_field' ) ) {
		return new WP_Error( 'acf_missing', 'Calculator data store unavailable.' );
	}

	$giving_rate = (float) get_field( 'giving_rate_per_person_hour', 'option' );
	if ( $giving_rate <= 0 ) {
		$giving_rate = 1.0;
	}

	$hours_yr         = $state['team'] * $state['daysPerWeek'] * $state['weeksPerYear'] * $state['hoursPerDay'];
	$giving_yr        = $hours_yr * $giving_rate;
	$hours_pp_yr      = $state['daysPerWeek'] * $state['weeksPerYear'] * $state['hoursPerDay'];
	$giving_pp        = $hours_pp_yr * $giving_rate;

	return [
		'calc'            => 'hours-to-impact',
		'hoursPerYear'    => $hours_yr,
		'givingPerYear'   => $giving_yr,
		'hoursPerPerson'  => $hours_pp_yr,
		'givingPerPerson' => $giving_pp,
		'givingRate'      => $giving_rate,
	];
}


/**
 * C1 — workspace-pricing recompute. Reads membership prices + annual prepay
 * discount from ACF Options (the SSOT), never from the client.
 *
 * Private-office methodology constants (rent/sqm, opex %, power W/m², MHFR,
 * admin load, etc.) stay in code — they're cited NZ methodology (§C1 plan).
 *
 * @param array $state sanitized { team, commitment, annual, members }
 * @return array|WP_Error
 */
function two57_calc_figures_workspace_pricing( array $state ) {
	if ( ! function_exists( 'get_field' ) ) {
		return new WP_Error( 'acf_missing', 'Calculator data store unavailable.' );
	}

	// Membership prices from the SSOT.
	$price = static function ( string $slug ): float {
		$p = (float) get_field( 'membership_' . $slug . '_monthly', 'option' );
		return $p > 0 ? $p : 0;
	};
	$prices = [
		'dedicated' => $price( 'dedicated' ),
		'flexi-5'   => $price( 'flexi_5' ),
		'flexi-4'   => $price( 'flexi_4' ),
		'flexi-3'   => $price( 'flexi_3' ),
		'flexi-2'   => $price( 'flexi_2' ),
		'flexi-1'   => $price( 'flexi_1' ),
	];
	$annual_discount = (float) get_field( 'annual_prepay_discount_pct', 'option' );
	if ( $annual_discount <= 0 ) {
		$annual_discount = 10;
	}

	// Private-office methodology (cited, stay in code — mirrors the engine).
	$t   = (float) $state['team'];
	$c   = max( 1, (float) $state['commitment'] );
	$sqm = $t * 10;

	$rent       = $sqm * 420;
	$opex       = $rent * 0.27;
	$furniture  = ( $t * 2000 ) / $c;
	$internet   = 2400;
	$power      = ( ( 50 * 8 * 230 * $sqm ) / 1000 ) * 0.30;
	$cleaning   = 45 * 1.2 * $sqm;
	$consumables = $t * 300;
	$insurance  = $t * 200;
	$mhfr       = ( ceil( $t / 12 ) * 445 ) / 2.5;
	$admin      = $t * 8 * 46 * 5 * 0.06 * 70;
	$legal      = 3500 / $c;
	$booking    = ( $t >= 10 ) ? ( 75 * 12 ) : 0;

	$private_total_yr = $rent + $opex + $furniture + $internet + $power
		+ $cleaning + $consumables + $insurance + $mhfr + $admin + $legal + $booking;

	// 257 — sum of memberships (annual_prepay_discount applies to Dedicated only).
	$ours_total_yr = 0;
	$ours_lines    = [];
	$member_number = 0;
	foreach ( $state['members'] as $tier ) {
		$member_number++;
		if ( '' === $tier ) {
			continue;
		}
		if ( 'dedicated' === $tier ) {
			$monthly = $state['annual'] ? $prices['dedicated'] * ( 1 - $annual_discount / 100 ) : $prices['dedicated'];
		} else {
			$monthly = $prices[ $tier ] ?? 0;
		}
		$ours_total_yr += $monthly * 12;
		$ours_lines[] = [ 'tier' => $tier, 'monthly' => $monthly, 'member' => $member_number ];
	}

	$annual_saving     = $private_total_yr - $ours_total_yr;
	$commitment_saving = $annual_saving * $c;
	$capital_tied_up   = ( $t * 2000 ) + 3500;

	return [
		'calc'              => 'workspace-pricing',
		'team'              => (int) $state['team'],
		'commitment'        => (int) $state['commitment'],
		'privateTotalYr'    => $private_total_yr,
		'oursTotalYr'       => $ours_total_yr,
		'oursMonthly'       => $ours_total_yr / 12,
		'oursLines'         => $ours_lines,
		'annualSaving'      => $annual_saving,
		'commitmentSaving'  => $commitment_saving,
		'capitalTiedUp'     => $capital_tied_up,
	];
}


/**
 * C2 — meet-pricing recompute. Reads rooms + addons + impact levers from the
 * ACF SSOT, never from the client. Mirrors the JS engine's compute() math —
 * changes here must be mirrored in assets/js/modules/meet-pricing.js and
 * vice-versa.
 *
 * @param array $state sanitized { people, room, duration, days[], addons{}, impact }
 * @return array|WP_Error
 */
function two57_calc_figures_meet_pricing( array $state ) {
	if ( ! function_exists( 'get_field' ) ) {
		return new WP_Error( 'acf_missing', 'Calculator data store unavailable.' );
	}

	$people   = (int) $state['people'];
	$room     = $state['room'];
	$duration = $state['duration'];
	$days     = $state['days'];
	$addons   = $state['addons'];

	$empty = [
		'calc'           => 'meet-pricing',
		'empty'          => true,
		'people'         => $people,
		'room'           => '',
		'roomName'       => '',
		'duration'       => '',
		'numDays'        => count( $days ),
		'totalHours'     => 0,
		'items'          => [],
		'total'          => 0,
		'discountAmt'    => 0,
		'impactDonation' => 0,
	];

	if ( ! $room || ! $duration || ! $days ) {
		return $empty;
	}

	// Lookup rates from ACF SSOT. Room slug → field key map is shared via
	// two57_meet_rooms() (same source as the wp_head injector + block.php).
	$room_info = two57_meet_rooms()[ $room ] ?? null;
	$room_key  = $room_info ? 'room_' . $room_info['key'] : '';
	$room_name = $room_info['name'] ?? $room;

	$rates = [ 'day' => 0, 'hour' => 0, 'evening' => 0 ];
	$cap   = 0;
	if ( $room_key ) {
		$rates['day']     = (float) get_field( $room_key . '_day', 'option' );
		$rates['hour']    = (float) get_field( $room_key . '_hour', 'option' );
		$rates['evening'] = (float) get_field( $room_key . '_evening', 'option' );
		$cap              = (int) get_field( $room_key . '_capacity', 'option' );
	}
	// People capped to the selected room's capacity (engine lets you size
	// up only; the email summary should never quote an over-capacity room).
	if ( $cap > 0 && $people > $cap ) {
		$people = $cap;
	}

	// Compute actual hours per day (duration='hour' path uses these).
	$parse_time = static function ( string $t ): float {
		$parts = explode( ':', $t );
		if ( count( $parts ) < 2 ) {
			return 0;
		}
		return (float) $parts[0] + (float) $parts[1] / 60;
	};
	$hours_for_day = static function ( array $d ) use ( $parse_time ): float {
		$start = $parse_time( $d['start'] ?? '09:00' );
		$end   = $parse_time( $d['end'] ?? '17:00' );
		return max( 0, $end - $start );
	};

	$num_days   = count( $days );
	$total_hrs  = array_reduce( $days, static function ( float $acc, array $d ) use ( $hours_for_day ): float {
		return $acc + $hours_for_day( $d );
	}, 0 );

	// Room cost + label.
	$room_cost   = 0;
	$room_label  = '';
	if ( 'hour' === $duration ) {
		$room_cost  = $rates['hour'] * $total_hrs;
		$room_label = $room_name . ' · ' . ( $num_days > 1 ? $num_days . ' days × ' : '' ) . round( $total_hrs, 2 ) . 'hrs × $' . number_format( $rates['hour'] ) . '/hr';
	} elseif ( 'day' === $duration ) {
		$room_cost  = $rates['day'] * $num_days;
		$room_label = $room_name . ' · ' . $num_days . ' day' . ( $num_days > 1 ? 's' : '' ) . ' × $' . number_format( $rates['day'] );
	} elseif ( 'evening' === $duration ) {
		$room_cost  = $rates['evening'] * $num_days;
		$room_label = $room_name . ' · ' . $num_days . ' evening' . ( $num_days > 1 ? 's' : '' ) . ' × $' . number_format( $rates['evening'] );
	}

	$items[] = [ 'label' => $room_label, 'value' => $room_cost ];

	// Tea + coffee — per-head × people × days.
	if ( ! empty( $addons['tea'] ) ) {
		$tea_type = (float) ( $addons['teaType'] ?? 5 );
		$tea_cost = $tea_type * $people * $num_days;
		$tea_desc = $tea_type >= 10 ? 'bottomless' : 'single serve';
		$tea_label = 'Tea + coffee · ' . $tea_desc . ' × ' . $people . 'pp' . ( $num_days > 1 ? ' × ' . $num_days . ' days' : '' );
		$items[] = [ 'label' => $tea_label, 'value' => $tea_cost ];
	}

	// Catering — per-head × people + organising fee, × days.
	if ( ! empty( $addons['catering'] ) ) {
		$organising_fee = (float) get_field( 'catering_organising_fee', 'option' );
		if ( $organising_fee <= 0 ) {
			$organising_fee = 100;
		}
		$per_head = (float) ( $addons['cateringPerHead'] ?? 25 );
		$catering_cost = ( $per_head * $people + $organising_fee ) * $num_days;
		$catering_label = 'Catering · ' . $people . 'pp × $' . number_format( $per_head ) . ' + $' . number_format( $organising_fee ) . ' organising' . ( $num_days > 1 ? ' × ' . $num_days . ' days' : '' );
		$items[] = [ 'label' => $catering_label, 'value' => $catering_cost ];
	}

	// Projector + sound — flat $50 each × days.
	$projector_flat = 0;
	$sound_flat     = 0;
	if ( ! empty( $addons['projector'] ) ) {
		$projector_flat = (float) get_field( 'av_projector_flat', 'option' );
		if ( $projector_flat <= 0 ) $projector_flat = 50;
		$items[] = [ 'label' => 'Projector' . ( $num_days > 1 ? ' × ' . $num_days . ' days' : '' ), 'value' => $projector_flat * $num_days ];
	}
	if ( ! empty( $addons['sound'] ) ) {
		$sound_flat = (float) get_field( 'av_sound_flat', 'option' );
		if ( $sound_flat <= 0 ) $sound_flat = 50;
		$items[] = [ 'label' => 'Sound system' . ( $num_days > 1 ? ' × ' . $num_days . ' days' : '' ), 'value' => $sound_flat * $num_days ];
	}

	$total = array_reduce( $items, static function ( float $acc, array $it ): float {
		return $acc + $it['value'];
	}, 0 );

	$discount_amt  = 0;
	$discount_pct  = (float) get_field( 'impact_discount_pct', 'option' );
	if ( $discount_pct <= 0 ) {
		$discount_pct = 50;
	}
	$discount_frac = $discount_pct / 100;

	if ( ! empty( $addons['impact'] ) ) {
		$discount_amt = $room_cost * $discount_frac;
		$total      -= $discount_amt;
		$items[]    = [
			'label'    => 'Impact Discount · ' . number_format( $discount_pct, 0 ) . '% off room',
			'value'    => -$discount_amt,
			'discount' => true,
		];
	}

	// Impact donation (giving $ funded by this booking): hours × people × rate.
	// Day + evening blocks are valued at 8/4 hours (engine + cited methodology);
	// hourly bookings use actual duration.
	$impact_hrs = $total_hrs;
	if ( 'day' === $duration ) {
		$impact_hrs = $num_days * 8;
	} elseif ( 'evening' === $duration ) {
		$impact_hrs = $num_days * 4;
	}
	$giving_rate       = (float) get_field( 'giving_rate_per_person_hour', 'option' );
	if ( $giving_rate <= 0 ) {
		$giving_rate = 1;
	}
	$impact_donation = round( $impact_hrs * $people * $giving_rate );

	return [
		'calc'           => 'meet-pricing',
		'empty'          => false,
		'people'         => $people,
		'room'           => $room,
		'roomName'       => $room_name,
		'duration'       => $duration,
		'numDays'        => $num_days,
		'totalHours'     => $total_hrs,
		'items'          => $items,
		'total'          => $total,
		'discountAmt'    => $discount_amt,
		'discountPct'    => $discount_pct,
		'impactDonation' => $impact_donation,
		'cap'            => $cap,
	];
}


/**
 * Compose the plain + HTML email for a calc's figures.
 *
 * @param string $calc
 * @param array  $figures
 * @param array  $state
 * @param string $page
 * @param string $to email
 * @return array { subject, plain, html, summary }
 */
function two57_calc_compose_email( string $calc, array $figures, array $state, string $page, string $to ): array {
	switch ( $calc ) {
		case 'hours-to-impact':
			return two57_calc_compose_hours_to_impact( $figures, $state, $page, $to );
		case 'workspace-pricing':
			return two57_calc_compose_workspace_pricing( $figures, $state, $page, $to );
		case 'meet-pricing':
			return two57_calc_compose_meet_pricing( $figures, $state, $page, $to );
	}

	return [];
}


/**
 * C6 — hours-to-impact email copy.
 */
function two57_calc_compose_hours_to_impact( array $figures, array $state, string $page, string $to ): array {
	$money = static function ( float $n ): string {
		return '$' . number_format( round( $n ) );
	};
	$hrs   = static function ( float $n ): string {
		return number_format( round( $n ) ) . ' hrs';
	};

	$summary = sprintf(
		'A team of %d, %d days a week for %d weeks at %d hours a day, funds %s of subsidised space a year.',
		$state['team'],
		$state['daysPerWeek'],
		$state['weeksPerYear'],
		$state['hoursPerDay'],
		$money( $figures['givingPerYear'] )
	);

	$link = home_url( $page );
	$link = add_query_arg( [
		'team'  => $state['team'],
		'days'  => $state['daysPerWeek'],
		'weeks' => $state['weeksPerYear'],
		'hours' => $state['hoursPerDay'],
	], $link );

	$subject = 'Your two/fiftyseven impact calculation';

	$plain = implode( "\n\n", [
		$summary,
		'Your team’s hours, a year: ' . $hrs( $figures['hoursPerYear'] ),
		'Subsidised space funded: ' . $money( $figures['givingPerYear'] ),
		'Per person, per year: ' . $hrs( $figures['hoursPerPerson'] ) . ' — ' . $money( $figures['givingPerPerson'] ),
		'at ' . $money( $figures['givingRate'] ) . ' per person-hour',
		'',
		'Save or forward this calculation: ' . $link,
		'',
		'—',
		'two/fiftyseven, Wellington · https://twofiftyseven.co/',
		'Contact policy: ' . home_url( '/contact-policy/' ),
	] );

	$html = implode( '', [
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">',
			'<strong style="color:#111827;">' . esc_html( $hrs( $figures['hoursPerYear'] ) ) . '</strong> — your team’s hours, a year<br>',
			'<strong style="color:#111827;">' . esc_html( $money( $figures['givingPerYear'] ) ) . '</strong> — subsidised space funded<br>',
			'<strong style="color:#111827;">' . esc_html( $hrs( $figures['hoursPerPerson'] ) ) . '</strong> per person, per year — ',
			'<strong style="color:#111827;">' . esc_html( $money( $figures['givingPerPerson'] ) ) . '</strong> at ' . esc_html( $money( $figures['givingRate'] ) ) . ' per person-hour',
		'</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;">',
			'<a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open and share your calculation →</a>',
		'</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br>',
			'<a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a>',
		'</p>',
	] );

	return [ 'subject' => $subject, 'summary' => $summary, 'plain' => $plain, 'html' => $html ];
}


/**
 * C1 — workspace-pricing email copy.
 */
function two57_calc_compose_workspace_pricing( array $figures, array $state, string $page, string $to ): array {
	$money = static function ( float $n ): string {
		return '$' . number_format( round( $n ) );
	};

	$member_count = count( array_filter( $state['members'], static function ( $tier ): bool {
		return '' !== $tier;
	} ) );

	$summary = sprintf(
		'A team of %d across %d membership%s comes to %s a year at two/fiftyseven — %s less than a private Wellington office.',
		$figures['team'],
		max( 1, $member_count ),
		1 === $member_count ? '' : 's',
		$money( $figures['oursTotalYr'] ),
		$money( max( 0, $figures['annualSaving'] ) )
	);

	$roster_plain = [];
	$roster_html  = [];
	foreach ( $figures['oursLines'] as $line ) {
		$tier_label = static function ( string $slug ): string {
			if ( 'dedicated' === $slug ) {
				return 'Dedicated Desk';
			}
			return 'Flexi ' . str_replace( 'flexi-', '', $slug ) . ' day' . ( 'flexi-1' === $slug ? '' : 's' ) . '/week';
		};
		$label = $tier_label( $line['tier'] );
		$member = $line['member'] ?? count( $roster_plain ) + 1;
		$roster_plain[] = sprintf( 'Member %d: %s · %s/mo', $member, $label, $money( $line['monthly'] ) );
		$roster_html[]  = sprintf(
			'Member %d: <strong style="color:#111827;">%s</strong> · %s/mo',
			$member,
			esc_html( $label ),
			esc_html( $money( $line['monthly'] ) )
		);
	}

	$link = home_url( $page );
	// Reproduce the engine's URL state so the link opens the same proposal.
	$desks = '';
	foreach ( $state['members'] as $tier ) {
		if ( '' === $tier ) {
			$desks .= 'x';
		} elseif ( 'dedicated' === $tier ) {
			$desks .= 'd';
		} else {
			$desks .= str_replace( 'flexi-', '', $tier );
		}
	}
	$link = add_query_arg( [
		'team'       => $figures['team'],
		'commitment' => $figures['commitment'],
		'annual'     => $state['annual'] ? 'true' : 'false',
		'desks'      => $desks,
	], $link );

	$subject = 'Your two/fiftyseven workspace pricing';

	$plain = implode( "\n\n", [
		$summary,
		'Your team’s monthly total: ' . $money( $figures['oursMonthly'] ) . '/mo',
		'Your team’s annual total: ' . $money( $figures['oursTotalYr'] ) . '/yr',
		'Private office benchmark: ' . $money( $figures['privateTotalYr'] ) . '/yr',
		'Savings vs a private office: ' . $money( max( 0, $figures['annualSaving'] ) ) . '/yr',
		'',
		'Memberships:',
		( $roster_plain ? implode( "\n", $roster_plain ) : '(none selected)' ),
		'',
		'Save or forward this calculation: ' . $link,
		'',
		'—',
		'two/fiftyseven, Wellington · https://twofiftyseven.co/',
		'Contact policy: ' . home_url( '/contact-policy/' ),
	] );

	$html = implode( '', [
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">',
			'<strong style="color:#111827;">' . esc_html( $money( $figures['oursMonthly'] ) ) . '</strong> /mo — your team’s monthly total<br>',
			'<strong style="color:#111827;">' . esc_html( $money( $figures['oursTotalYr'] ) ) . '</strong> /yr — your team’s annual total<br>',
			'<strong style="color:#111827;">' . esc_html( $money( $figures['privateTotalYr'] ) ) . '</strong> /yr — private office benchmark<br>',
			'<strong style="color:#111827;">' . esc_html( $money( max( 0, $figures['annualSaving'] ) ) ) . '</strong> /yr — savings vs a private office',
		'</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">Memberships:<br>' . implode( '<br>', $roster_html ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;">',
			'<a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open and share your calculation →</a>',
		'</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br>',
			'<a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a>',
		'</p>',
	] );

	return [ 'subject' => $subject, 'summary' => $summary, 'plain' => $plain, 'html' => $html ];
}


/**
 * C2 — meet-pricing email copy.
 *
 * @param array  $figures Re-rendered by two57_calc_figures_meet_pricing().
 * @param array  $state   Sanitized by two57_calc_sanitize_meet_pricing().
 * @param string $page    Pathname the calc sits on.
 * @param string $to      Recipient email.
 * @return array { subject, summary, plain, html }
 */
function two57_calc_compose_meet_pricing( array $figures, array $state, string $page, string $to ): array {
	$money = static function ( float $n ): string {
		return '$' . number_format( round( $n ) );
	};

	$empty_email = $figures['empty'] ?? false;
	$room        = $figures['roomName'] ?? '';
	$people      = $figures['people'] ?? 0;
	$duration    = $figures['duration'] ?? '';
	$num_days    = $figures['numDays'] ?? 0;

	// Subject + 1-line summary depend on whether the calc has a big-enough state.
	if ( $empty_email || ! $room || ! $duration ) {
		$summary = 'two/fiftyseven meeting quote — pick a room and a duration and we\'ll show your itemised price.';
		$subject = 'Your two/fiftyseven meeting quote';

		$link = home_url( $page );

		$plain = implode( "\n\n", [
			$summary,
			'Open the calculator and rebuild your quote: ' . $link,
			'',
			'—',
			'two/fiftyseven, Wellington · https://twofiftyseven.co/',
			'Contact policy: ' . home_url( '/contact-policy/' ),
		] );

		$html = implode( '', [
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;"><a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open your quote →</a></p>',
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br><a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a></p>',
		] );

		return [ 'subject' => $subject, 'summary' => $summary, 'plain' => $plain, 'html' => $html ];
	}

	$duration_word = [ 'hour' => 'an hourly', 'day' => 'a full-day', 'evening' => 'an evening' ][ $duration ] ?? 'a';

	$summary = sprintf(
		'%s at two/fiftyseven — %s × %d day%s for %d people — totals %s (excl. GST).',
		$room,
		$duration_word,
		$num_days,
		$num_days > 1 ? 's' : '',
		$people,
		$money( (float) $figures['total'] )
	);

	// Rebuild the share link params (mirror meet-pricing.js writeURL).
	$link_params = [
		'people' => $people,
		'room'   => $state['room'],
		'dur'    => $state['duration'],
	];
	$days_enc = [];
	foreach ( $state['days'] as $day ) {
		$days_enc[] = $day['date'] . '|' . $day['start'] . '-' . $day['end'];
	}
	if ( $days_enc ) {
		$link_params['days'] = implode( ',', $days_enc );
	}
	$addon_tokens = [];
	if ( ! empty( $state['addons']['tea'] ) ) {
		$addon_tokens[] = (float) ( $state['addons']['teaType'] ?? 5 ) >= 10 ? 'tea-bottomless' : 'tea-single';
	}
	if ( ! empty( $state['addons']['projector'] ) ) {
		$addon_tokens[] = 'projector';
	}
	if ( ! empty( $state['addons']['sound'] ) ) {
		$addon_tokens[] = 'sound';
	}
	if ( ! empty( $state['addons']['catering'] ) ) {
		$addon_tokens[] = 'catering-' . (string) ( $state['addons']['cateringPerHead'] ?? 25 );
	}
	if ( $addon_tokens ) {
		$link_params['addons'] = implode( ',', $addon_tokens );
	}
	if ( ! empty( $state['addons']['impact'] ) || ! empty( $state['impact'] ) ) {
		$link_params['impact'] = '1';
	}

	$link = add_query_arg( $link_params, home_url( $page ) );

	$subject = 'Your two/fiftyseven meeting quote';

	$lines_plain = [];
	$lines_html  = [];
	foreach ( $figures['items'] as $item ) {
		$value = $item['value'];
		$sign  = $value < 0 ? '-' : '';
		$lines_plain[] = $item['label'] . ': ' . $sign . $money( abs( (float) $value ) );
		$lines_html[]  = sprintf(
			'%s — <strong style="color:#111827;">%s%s</strong>',
			esc_html( $item['label'] ),
			$sign,
			esc_html( $money( abs( (float) $value ) ) )
		);
	}

	$plain = implode( "\n\n", [
		$summary . ' — ' . $money( (float) $figures['total'] ) . ' excl. GST.',
		'Itemised quote:',
		implode( "\n", $lines_plain ),
		'',
		'Your booking also funds ' . $money( (float) $figures['impactDonation'] ) . ' of subsidised space for charities + community orgs.',
		'',
		'Open or share this quote: ' . $link,
		'',
		'—',
		'two/fiftyseven, Wellington · https://twofiftyseven.co/',
		'Contact policy: ' . home_url( '/contact-policy/' ),
	] );

	$html = implode( '', [
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;color:#111827;font-weight:600;">' . esc_html( $money( (float) $figures['total'] ) ) . ' <span style="font-size:14px;font-weight:400;color:#6b7280;">excl. GST</span></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">Itemised quote:<br>' . implode( '<br>', $lines_html ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">Your booking also funds <strong style="color:#111827;">' . esc_html( $money( (float) $figures['impactDonation'] ) ) . '</strong> of subsidised space for charities + community orgs.</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;"><a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open and share your quote →</a></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br><a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a></p>',
	] );

	return [ 'subject' => $subject, 'summary' => $summary, 'plain' => $plain, 'html' => $html ];
}


/**
 * Send an email via MailPoet's MailerFactory when available (keeps the
 * configured sending method / bounce handling), falling back to wp_mail().
 *
 * @param string $to
 * @param array  $body { subject, plain, html }
 * @return true|WP_Error
 */
function two57_calc_share_send_email( string $to, array $body ) {
	if ( empty( $body['subject'] ) || empty( $body['plain'] ) ) {
		return new WP_Error( 'compose_failed', 'Could not compose email.' );
	}

	// QA hook — when TWO57_CALC_EMAIL_LOG is defined, log the composed email
	// to error_log instead of attempting delivery. Lets us verify the full
	// pipeline (REST + recompute + compose) against a real mail trap later.
	if ( defined( 'TWO57_CALC_EMAIL_LOG' ) && TWO57_CALC_EMAIL_LOG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[two57 calc-share-email] LOG TO: ' . $to );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[two57 calc-share-email] SUBJECT: ' . $body['subject'] );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[two57 calc-share-email] BODY: ' . str_replace( "\n", ' | ', $body['plain'] ) );
		return true;
	}

	// Prefer MailPoet's sender so the email uses the MailPoet-sending method.
	if ( class_exists( '\MailPoet\DI\ContainerWrapper' ) && class_exists( '\MailPoet\Mailer\MailerFactory' ) ) {
		try {
			$mailer = \MailPoet\DI\ContainerWrapper::getInstance()
				->get( \MailPoet\Mailer\MailerFactory::class )
				->getDefaultMailer();

			$newsletter = [
				'subject' => $body['subject'],
				'body'    => [
					'html' => $body['html'] ?? $body['plain'],
					'text' => $body['plain'],
				],
			];

			$result = $mailer->send( $newsletter, [ 'email' => $to, 'full_name' => '' ] );
			if ( is_array( $result ) && ! empty( $result['response'] ) ) {
				return true;
			}
			if ( is_array( $result ) && isset( $result['error'] ) ) {
				return new WP_Error( 'mailer_error', $result['error']->getMessage() );
			}
		} catch ( \Throwable $e ) {
			// Fall through to wp_mail() — MailPoet may not be configured.
		}
	}

	// Fallback — plain WordPress mail.
	$sent = wp_mail(
		$to,
		$body['subject'],
		$body['html'] ?? $body['plain'],
		[ 'Content-Type: text/html; charset=UTF-8' ]
	);

	return $sent ? true : new WP_Error( 'wp_mail_failed', 'wp_mail returned false.' );
}


/**
 * Capture a consented lead on MailPoet's shared "Calculator leads" list,
 * stamped with the calc_source custom field. List + custom field are
 * auto-created on first use so demo/local needs no manual setup.
 *
 * No double-opt-in email: this is a transactional send to a lead who
 * explicitly opted into the contact policy (§6.4).
 *
 * @param string $email
 * @param string $calc
 * @return bool
 */
function two57_calc_capture_lead( string $email, string $calc ): bool {
	if ( ! class_exists( '\MailPoet\DI\ContainerWrapper' ) ) {
		return false;
	}

	try {
		$container = \MailPoet\DI\ContainerWrapper::getInstance();

		$segments      = $container->get( \MailPoet\Segments\SegmentsRepository::class );
		$subscribers   = $container->get( \MailPoet\Subscribers\SubscribersRepository::class );
		$subSegments   = $container->get( \MailPoet\Subscribers\SubscriberSegmentRepository::class );
		$save          = $container->get( \MailPoet\Subscribers\SubscriberSaveController::class );
		$customFields  = $container->get( \MailPoet\CustomFields\CustomFieldsRepository::class );

		// 1. "Calculator leads" list — find or create.
		$segment = $segments->findOneBy( [ 'name' => 'Calculator leads' ] );
		if ( ! $segment ) {
			$segment = $segments->createOrUpdate( 'Calculator leads', '', \MailPoet\Entities\SegmentEntity::TYPE_DEFAULT );
		}

		// 2. Subscriber — find or create, subscribed (no double opt-in).
		$existing = $subscribers->findOneBy( [ 'email' => $email ] );
		$subscriber = $save->createOrUpdate( [
			'email'  => $email,
			'status' => \MailPoet\Entities\SubscriberEntity::STATUS_SUBSCRIBED,
		], $existing );

		// 3. Attach to the list if not already.
		$subSegments->createOrUpdate( $subscriber, $segment, \MailPoet\Entities\SubscriberEntity::STATUS_SUBSCRIBED );

		// 4. Stamp calc_source custom field — auto-created on first use.
		$field = $customFields->findOneBy( [ 'name' => 'calc_source' ] );
		if ( ! $field ) {
			$field = $customFields->createOrUpdate( [
				'name'   => 'calc_source',
				'type'   => 'text',
				'params' => [ 'label' => 'Calculator source' ],
			] );
		}

		if ( $field ) {
			$save->updateCustomFields( [ 'cf_' . $field->getId() => $calc ], $subscriber );
		}

		return true;
	} catch ( \Throwable $e ) {
		// Lead capture is best-effort — never fail the user's email over it.
		error_log( '[two57 calc-share-email] lead capture failed: ' . $e->getMessage() );
		return false;
	}
}


/**
 * Small helper for uniform WP_Error JSON responses.
 *
 * @param WP_Error $error
 * @return WP_REST_Response
 */
function two57_calc_share_respond( WP_Error $error ): WP_REST_Response {
	return new WP_REST_Response( [ 'success' => false, 'message' => $error->get_error_message() ], 400 );
}