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

	$calc_raw = $params['calc'] ?? '';
	$calc = is_scalar( $calc_raw ) ? sanitize_key( (string) $calc_raw ) : '';

	// 2. Email validation.
	$email_raw = $params['email'] ?? '';
	$email = is_scalar( $email_raw ) ? sanitize_email( (string) $email_raw ) : '';
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
		case 'office-carbon':
			$figures = two57_calc_figures_office_carbon( $state );
			break;
		case 'meeting-costs':
			$figures = two57_calc_figures_meeting_costs( $state );
			break;
		case 'office-costs':
			$figures = two57_calc_figures_office_costs( $state );
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
		case 'meeting-costs':
			return two57_calc_sanitize_meeting_costs( $state );
		case 'office-carbon':
			// Zero-start (mirrors the engine): a 0-team email is valid but
			// figures read "0 t". Bounds mirror the engine's Max/Max clamp.
			return [
				'team'         => two57_calc_int( $state['team'] ?? 0, 0, 15 ),
				'daysPerWeek'  => two57_calc_int( $state['days'] ?? $state['daysPerWeek'] ?? 0, 0, 5 ),
				'weeksPerYear' => two57_calc_int( $state['weeks'] ?? $state['weeksPerYear'] ?? 0, 0, 52 ),
				'hoursPerDay'  => two57_calc_float( $state['hours'] ?? $state['hoursPerDay'] ?? 0, 0, 24 ),
			];
		case 'office-costs':
			return two57_calc_sanitize_office_costs( $state );
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
 * C4 — meeting-costs state sanitisation. Normalises + bound-checks the
 * comparison state the JS engine sends on email-submit. Group size clamps
 * 0-200 (zero-start mirrors the engine); per-day start/end validate as
 * 24h HH:MM (cap 14 days — a fortnight); catering/AV/materials flags coerce
 * to bool; fac/setup whitelist; custom lines get a sanitized label + a
 * positive clamped value. Never returns WP_Error (meets the contract).
 *
 * @param array $state raw { size, days[], catering{}, av{}, materials{}, fac, setup, custom[], impact }
 * @return array sanitized state shape
 */
function two57_calc_sanitize_meeting_costs( array $state ): array {
	$size = two57_calc_int( $state['size'] ?? 0, 0, 200 );

	$fac_allowed = [ 'none', 'half', 'full', 'senior' ];
	$fac = is_string( $state['fac'] ?? null ) ? sanitize_key( $state['fac'] ) : 'none';
	if ( ! in_array( $fac, $fac_allowed, true ) ) {
		$fac = 'none';
	}

	$setup_allowed = [ 'standard', 'complex' ];
	$setup = is_string( $state['setup'] ?? null ) ? sanitize_key( $state['setup'] ) : 'standard';
	if ( ! in_array( $setup, $setup_allowed, true ) ) {
		$setup = 'standard';
	}

	// Per-day: validate 24h HH:MM start/end + carry the chosen date through
	// so the emailed share link reproduces the exact days on screen. Cap at
	// 14 (fortnight — same ceiling as meet-pricing; a longer email is
	// meaningless).
	$raw_days = is_array( $state['days'] ?? null ) ? $state['days'] : [];
	if ( ! $raw_days ) {
		$raw_days = [ [ 'start' => '09:00', 'end' => '17:00' ] ];
	}
	$days = [];
	foreach ( $raw_days as $day ) {
		if ( ! is_array( $day ) ) {
			continue;
		}
		$date  = sanitize_text_field( (string) ( $day['date'] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}
		$start = sanitize_text_field( (string) ( $day['start'] ?? '09:00' ) );
		$end   = sanitize_text_field( (string) ( $day['end'] ?? '17:00' ) );
		if ( ! preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $start ) ) $start = '09:00';
		if ( ! preg_match( '/^([01]?\d|2[0-3]):[0-5]\d$/', $end ) )   $end   = '17:00';
		$days[] = [ 'date' => $date, 'start' => $start, 'end' => $end ];
		if ( count( $days ) >= 14 ) break;
	}
	if ( ! $days ) {
		$days = [ [ 'date' => gmdate( 'Y-m-d' ), 'start' => '09:00', 'end' => '17:00' ] ];
	}

	$raw_catering  = is_array( $state['catering'] ?? null ) ? $state['catering'] : [];
	$raw_av        = is_array( $state['av'] ?? null ) ? $state['av'] : [];
	$raw_materials = is_array( $state['materials'] ?? null ) ? $state['materials'] : [];

	// Custom lines — sanitize label (max 60 chars), clamp value to a
	// positive float. Rows with an empty label or zero value are dropped
	// (mirrors the engine's "cl && cl.label && cl.value > 0" filter).
	$custom = [];
	foreach ( (array) ( $state['custom'] ?? [] ) as $line ) {
		if ( ! is_array( $line ) || count( $custom ) >= 10 ) {
			continue;
		}
		$label = sanitize_text_field( (string) ( $line['label'] ?? '' ) );
		if ( mb_strlen( $label ) > 60 ) {
			$label = mb_substr( $label, 0, 60 );
		}
		$value = isset( $line['value'] ) ? two57_calc_float( $line['value'], 0, 1000000 ) : 0;
		if ( '' === $label || $value <= 0 ) {
			continue;
		}
		$custom[] = [ 'label' => $label, 'value' => $value ];
	}

	return [
		'size'      => $size,
		'days'      => $days,
		'catering'  => [
			'tea'         => ! empty( $raw_catering['tea'] ),
			'breakfast'   => ! empty( $raw_catering['breakfast'] ),
			'lunchLight'  => ! empty( $raw_catering['lunchLight'] ),
			'lunchHearty' => ! empty( $raw_catering['lunchHearty'] ),
			'afternoon'   => ! empty( $raw_catering['afternoon'] ),
			'drinks'      => ! empty( $raw_catering['drinks'] ),
		],
		'av'        => [
			'projector' => ! empty( $raw_av['projector'] ),
			'sound'     => ! empty( $raw_av['sound'] ),
		],
		'materials' => [
			'boards'  => ! empty( $raw_materials['boards'] ),
			'postits' => ! empty( $raw_materials['postits'] ),
			'printing'=> ! empty( $raw_materials['printing'] ),
		],
		'fac'       => $fac,
		'setup'     => $setup,
		'custom'    => $custom,
		'impact'    => ! empty( $state['impact'] ),
	];
}


/**
 * C3 — office-costs state sanitisation. Normalises + bound-checks the office
 * budget state the JS engine sends on email-submit. Team clamps 0-15
 * (zero-start mirrors the engine's empty card); per-member days are a list
 * of 1-5 integers capped at the team size; grade + precinct whitelist to the
 * engine's modifier sets; every cost input clamps to its block's min/max
 * (falling back to the cited default when absent). Custom lines get a
 * sanitized label + a positive clamped value; rows with an empty label or
 * zero value are dropped (mirrors the engine's readState filter).
 *
 * @param array $state raw { team, days[], grade, precinct, sqmPerPerson, rentPerSqmPerYr, ..., bookingSoftware, customLines[] }
 * @return array sanitized state shape — never WP_Error (meets the contract)
 */
function two57_calc_sanitize_office_costs( array $state ): array {
	$grades_allowed   = [ 'A-grade', 'B-grade fitted', 'B-grade unfitted', 'C-grade' ];
	$precincts_allowed = [ 'CBD core', 'CBD fringe', 'Te Aro', 'Thorndon', 'Lambton', 'Kelburn', 'Mt Vic' ];

	$team = two57_calc_int( $state['team'] ?? 0, 0, 15 );

	// Per-member days/week — list of 1-5, capped at the team count (the
	// engine pads a short list with the 5-day default, so a long one wins).
	$days = [];
	foreach ( (array) ( $state['days'] ?? [] ) as $day ) {
		$days[] = two57_calc_int( $day, 1, 5 );
		if ( count( $days ) >= max( 1, $team ) ) break;
	}
	if ( ! $days ) {
		$days = array_fill( 0, max( 1, $team ), 5 );
	}

	$grade = is_string( $state['grade'] ?? null ) ? sanitize_text_field( (string) $state['grade'] ) : '';
	if ( ! in_array( $grade, $grades_allowed, true ) ) {
		$grade = 'B-grade fitted';
	}

	$precinct = is_string( $state['precinct'] ?? null ) ? trim( (string) $state['precinct'] ) : '';
	if ( ! in_array( $precinct, $precincts_allowed, true ) ) {
		$precinct = 'CBD core';
	}

	// Custom lines — sanitize label (max 60 chars), clamp value to a
	// positive float. Rows with an empty label or zero value are dropped.
	$custom = [];
	foreach ( (array) ( $state['customLines'] ?? $state['custom'] ?? [] ) as $line ) {
		if ( ! is_array( $line ) || count( $custom ) >= 10 ) {
			continue;
		}
		$label = sanitize_text_field( (string) ( $line['label'] ?? '' ) );
		if ( mb_strlen( $label ) > 60 ) {
			$label = mb_substr( $label, 0, 60 );
		}
		$value = isset( $line['value'] ) ? two57_calc_float( $line['value'], 0, 1000000 ) : 0;
		if ( '' === $label || $value <= 0 ) {
			continue;
		}
		$custom[] = [ 'label' => $label, 'value' => $value ];
	}

	return [
		'team'                   => $team,
		'days'                   => $days,
		'grade'                  => $grade,
		'precinct'               => $precinct,
		'sqmPerPerson'           => two57_calc_float( $state['sqmPerPerson'] ?? 9, 6, 15 ),
		'rentPerSqmPerYr'        => two57_calc_float( $state['rentPerSqmPerYr'] ?? 310, 120, 570 ),
		'outgoingsPctOfRent'     => two57_calc_float( $state['outgoingsPctOfRent'] ?? 0.27, 0.2, 0.35 ),
		'internetPerMo'          => two57_calc_float( $state['internetPerMo'] ?? 200, 99, 400 ),
		'powerWattsPerSqm'       => two57_calc_float( $state['powerWattsPerSqm'] ?? 50, 40, 70 ),
		'powerHoursPerYear'      => two57_calc_float( $state['powerHoursPerYear'] ?? 1840, 1500, 2400 ),
		'powerPricePerKwh'       => two57_calc_float( $state['powerPricePerKwh'] ?? 0.30, 0.22, 0.42 ),
		'cleaningHoursPerSqmYr'  => two57_calc_float( $state['cleaningHoursPerSqmYr'] ?? 1.2, 1.0, 1.5 ),
		'cleaningPerHour'        => two57_calc_float( $state['cleaningPerHour'] ?? 45, 38, 55 ),
		'kbPerPersonPerYr'       => two57_calc_float( $state['kbPerPersonPerYr'] ?? 300, 200, 450 ),
		'insurancePerPersonPerYr'=> two57_calc_float( $state['insurancePerPersonPerYr'] ?? 200, 150, 400 ),
		'firstAidPerPersonPerYr' => two57_calc_float( $state['firstAidPerPersonPerYr'] ?? 28, 15, 50 ),
		'fireWardenPerPersonPerYr'=> two57_calc_float( $state['fireWardenPerPersonPerYr'] ?? 18, 10, 35 ),
		'furniturePerPerson'     => two57_calc_float( $state['furniturePerPerson'] ?? 2000, 1200, 3500 ),
		'furnitureAmortYrs'      => two57_calc_float( $state['furnitureAmortYrs'] ?? 5, 3, 10 ),
		'adminPctOfHours'        => two57_calc_float( $state['adminPctOfHours'] ?? 0.06, 0.04, 0.10 ),
		'adminLoadedHourly'      => two57_calc_float( $state['adminLoadedHourly'] ?? 70, 55, 90 ),
		'leaseLegalsOneOff'      => two57_calc_float( $state['leaseLegalsOneOff'] ?? 3500, 2000, 6000 ),
		'leaseTermYears'         => two57_calc_float( $state['leaseTermYears'] ?? 3, 1, 10 ),
		'bookingSoftware'        => ! empty( $state['bookingSoftware'] ),
		'bookingSoftwareCost'    => two57_calc_float( $state['bookingSoftwareCost'] ?? 8, 5, 15 ),
		'customLines'            => $custom,
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
 * C5 — office-carbon recompute. Methodology values are LOCKED per the calc
 * redesign brief (Tadpole ACE 2025 emission factors); they are not
 * admin-editable, so they stay in code and never come from the client.
 *
 * Mirrors the office-carbon.js engine's compute() + formatters.
 *
 * @param array $state sanitized { team, daysPerWeek, weeksPerYear, hoursPerDay }
 * @return array
 */
function two57_calc_figures_office_carbon( array $state ): array {
	$grid_kgco2e_per_kwh  = 0.1011; // Tadpole ACE 2025
	$line_loss_kgco2e_kwh = 0.0077; // Tadpole ACE 2025
	$power_w_per_sqm      = 50;     // BRANZ commercial office benchmark
	$sqm_per_person       = 10;     // GPG + BCO standard
	$office_days_yr       = 230;    // standard NZ working year
	$waste_kg_per_day     = 0.5;    // Wellington office mid
	$waste_kgco2e_per_kg  = 0.584;  // landfill w/ gas recovery, Tadpole ACE 2025
	$commute_kgco2e_day   = 0.3;    // Wellington 15km RT mixed EV/ICE mid
	$building_footprint   = 6.5;    // 257 in-office annual measured (tCO₂e)
	$building_capacity    = 80;     // approx daily occupancy
	$offset_ratio_public  = 2.0;    // 200% — the locked user-facing figure

	$person_day_257 = ( $building_footprint * 1000 ) / ( $building_capacity * $office_days_yr );

	$team = (int) $state['team'];
	$d    = (int) $state['daysPerWeek'];
	$w    = (int) $state['weeksPerYear'];
	$hpd  = (float) $state['hoursPerDay'];

	$sqm = $team * $sqm_per_person;
	$person_days = $team * $d * $w;
	$person_hours = $person_days * $hpd;

	$private_power_kg  = ( ( $power_w_per_sqm * $hpd * $w * $d * $sqm ) / 1000 ) * ( $grid_kgco2e_per_kwh + $line_loss_kgco2e_kwh );
	$private_waste_kg  = $person_days * $waste_kg_per_day * $waste_kgco2e_per_kg;
	$private_commute_kg = $person_days * $commute_kgco2e_day;
	$private_total_kg  = $private_power_kg + $private_waste_kg + $private_commute_kg;

	$ours_total_kg    = $person_days * $person_day_257;
	$ours_offset_kg   = $ours_total_kg * ( 1 - $offset_ratio_public ); // signed, negative
	$ours_positive_kg = abs( $ours_offset_kg );
	$saved_kg         = $private_total_kg - $ours_total_kg;
	$net_avoided_kg   = $private_total_kg - $ours_offset_kg;

	return [
		'calc'          => 'office-carbon',
		'team'          => $team,
		'daysPerWeek'   => $d,
		'weeksPerYear'  => $w,
		'hoursPerDay'   => $hpd,
		'sqm'           => $sqm,
		'personDays'    => $person_days,
		'personHours'   => $person_hours,
		'privatePower'  => $private_power_kg,
		'privateWaste'  => $private_waste_kg,
		'privateCommute'=> $private_commute_kg,
		'privateTotal'  => $private_total_kg,
		'oursTotal'     => $ours_total_kg,
		'oursOffset'    => $ours_offset_kg,
		'oursPositive'  => $ours_positive_kg,
		'savedVsPrivate'=> $saved_kg,
		'netAvoided'    => $net_avoided_kg,
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
 * C4 — meeting-costs recompute. Mirrors the meeting-costs.js engine's
 * compute() + deriveDuration + pickSpace math; industry bands stay in code
 * (cited), the 2/57 space + addon rates come from the ACF SSOT, never the
 * client. Changes here must be mirrored in
 * assets/js/modules/meeting-costs.js and vice-versa.
 *
 * @param array $state sanitized { size, days[], catering{}, av{}, materials{}, fac, setup, custom[], impact }
 * @return array|WP_Error
 */
function two57_calc_figures_meeting_costs( array $state ) {
	if ( ! function_exists( 'get_field' ) ) {
		return new WP_Error( 'acf_missing', 'Calculator data store unavailable.' );
	}

	// --- Methodology constants (cited, stay in code) ---
	$ind = [
		'ROOM' => [
			'half-day'  => [ 'small' => [ 350, 650 ],  'mid' => [ 550, 1100 ], 'large' => [ 900, 2200 ] ],
			'full-day'  => [ 'small' => [ 550, 1100 ], 'mid' => [ 850, 1900 ], 'large' => [ 1500, 3800 ] ],
			'multi-day' => [ 'small' => [ 550, 1100 ], 'mid' => [ 850, 1900 ], 'large' => [ 1500, 3800 ] ], // PER DAY
			'evening'   => [ 'small' => [ 350, 750 ],  'mid' => [ 600, 1300 ], 'large' => [ 1100, 2600 ] ],
			'hourly'    => [ 'small' => [ 90, 180 ],   'mid' => [ 140, 280 ],  'large' => [ 220, 480 ] ],
		],
		'TEA'         => [ 4, 8 ],
		'BREAKFAST'   => [ 12, 22 ],
		'LUNCH_LIGHT' => [ 20, 25 ],
		'LUNCH_HEARTY'=> [ 30, 40 ],
		'AFTERNOON'   => [ 8, 15 ],
		'DRINKS'      => [ 25, 55 ],
		'AV_PROJECTOR'=> [ 180, 350 ],
		'AV_SOUND'    => [ 280, 550 ],
		'FAC_HALF'    => [ 1500, 4500 ],
		'FAC_FULL'    => [ 3500, 8000 ],
		'FAC_SENIOR'  => [ 5000, 9000 ],
		'MAT_WHITEBOARDS' => [ 40, 120 ],
		'MAT_POSTITS'     => [ 25, 60 ],
		'MAT_PRINTING'    => [ 40, 150 ],
		'SETUP_STD'       => [ 80, 180 ],
		'SETUP_COMPLEX'   => [ 250, 600 ],
	];

	// --- Helpers (mirror engine) ---
	$size     = (int) $state['size'];
	$days     = $state['days'];
	$catering = $state['catering'];
	$av       = $state['av'];
	$materials= $state['materials'];
	$fac      = $state['fac'];
	$setup    = $state['setup'];

	$parse_time = static function ( string $t ): ?float {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $t, $m ) ) {
			return null;
		}
		return (float) $m[1] * 60 + (float) $m[2];
	};
	$hours_for_day = static function ( array $d ) use ( $parse_time ): float {
		$s = $parse_time( $d['start'] ?? '09:00' );
		$e = $parse_time( $d['end'] ?? '17:00' );
		if ( $s === null || $e === null || $e <= $s ) {
			return 0;
		}
		return ( $e - $s ) / 60;
	};

	// Derive duration from populated day rows (mirror deriveDuration).
	$per_day_hours = array_map( $hours_for_day, $days );
	$populated     = [];
	foreach ( $per_day_hours as $i => $h ) {
		if ( $h > 0 ) {
			$populated[] = $i;
		}
	}
	$total_hours  = array_sum( $per_day_hours );
	$duration     = '';
	$multi_days   = 1;
	if ( count( $populated ) >= 2 ) {
		$duration   = 'multi-day';
		$multi_days = count( $populated );
	} elseif ( count( $populated ) === 1 ) {
		$first  = $days[ $populated[0] ];
		$start  = $parse_time( $first['start'] ?? '09:00' );
		if ( $start !== null && $start >= 17 * 60 ) {
			$duration = 'evening';
		} elseif ( $total_hours < 3 ) {
			$duration = 'hourly';
		} elseif ( $total_hours < 6 ) {
			$duration = 'half-day';
		} else {
			$duration = 'full-day';
		}
	}

	$empty = [
		'calc'        => 'meeting-costs',
		'empty'       => true,
		'size'        => $size,
		'duration'    => $duration,
		'hours'       => round( $total_hours * 10 ) / 10,
		'multiDays'   => $multi_days,
		'industry'    => [ 'low' => 0, 'high' => 0, 'lines' => [] ],
		'ours'        => [ 'total' => 0, 'lines' => [], 'spaceKey' => null, 'spaceName' => '' ],
		'saving'      => [ 'low' => 0, 'high' => 0 ],
	];

	// Zero-start: no size or no populated day → nothing rendered.
	if ( $size <= 0 || '' === $duration ) {
		return $empty;
	}

	// Size band + duration factor (mirror sizeBand / durationFactor).
	$band = $size <= 10 ? 'small' : ( $size <= 36 ? 'mid' : 'large' );
	$dur_factor = 1.0;
	if ( 'hourly' === $duration )    $dur_factor = 0.4;
	elseif ( 'evening' === $duration ) $dur_factor = 0.7;
	elseif ( 'full-day' === $duration ) $dur_factor = 1.3;
	elseif ( 'multi-day' === $duration ) $dur_factor = 1.3 * max( 2, $multi_days );

	// --- INDUSTRY-STANDARD LINES (low + high band) ---
	$lines   = [];
	$ind_low = 0;
	$ind_high = 0;

	$room_band = $ind['ROOM'][ $duration ][ $band ];
	$room_mult = 'multi-day' === $duration ? $multi_days : 1;
	$room_low  = $room_band[0] * $room_mult;
	$room_high = $room_band[1] * $room_mult;
	$room_note = 'Wellington venue · ' . $duration . ( 'multi-day' === $duration ? ' · ' . $multi_days . ' days' : '' ) . ' · ' . $size . ( 1 === $size ? ' person' : ' people' );
	$lines[] = [ 'key' => 'room', 'label' => 'Room hire', 'note' => $room_note, 'low' => $room_low, 'high' => $room_high, 'src' => 'room' ];
	$ind_low  += $room_low;
	$ind_high += $room_high;

	$cat_low   = 0;
	$cat_high  = 0;
	$cat_notes = [];
	$cat_map = [
		'tea'         => 'tea+coffee',
		'breakfast'   => 'breakfast',
		'lunchLight'  => 'light lunch',
		'lunchHearty' => 'hearty lunch',
		'afternoon'   => 'afternoon tea',
		'drinks'      => 'drinks',
	];
	$cat_band = [
		'tea'         => $ind['TEA'],
		'breakfast'   => $ind['BREAKFAST'],
		'lunchLight'  => $ind['LUNCH_LIGHT'],
		'lunchHearty' => $ind['LUNCH_HEARTY'],
		'afternoon'   => $ind['AFTERNOON'],
		'drinks'      => $ind['DRINKS'],
	];
	foreach ( $cat_map as $key => $label ) {
		if ( empty( $catering[ $key ] ) ) {
			continue;
		}
		$cat_low   += $cat_band[ $key ][0] * $size;
		$cat_high  += $cat_band[ $key ][1] * $size;
		$cat_notes[] = $label;
	}
	if ( 'multi-day' === $duration ) {
		$cat_low  *= $multi_days;
		$cat_high *= $multi_days;
	}
	if ( $cat_low + $cat_high > 0 ) {
		$lines[] = [ 'key' => 'catering', 'label' => 'Catering', 'note' => implode( ' · ', $cat_notes ) . ' × ' . $size, 'low' => $cat_low, 'high' => $cat_high, 'src' => 'catering' ];
		$ind_low  += $cat_low;
		$ind_high += $cat_high;
	}

	$av_low   = 0;
	$av_high  = 0;
	$av_notes = [];
	if ( ! empty( $av['projector'] ) ) {
		$av_low   += $ind['AV_PROJECTOR'][0] * $dur_factor;
		$av_high  += $ind['AV_PROJECTOR'][1] * $dur_factor;
		$av_notes[] = 'projector';
	}
	if ( ! empty( $av['sound'] ) ) {
		$av_low   += $ind['AV_SOUND'][0] * $dur_factor;
		$av_high  += $ind['AV_SOUND'][1] * $dur_factor;
		$av_notes[] = 'sound';
	}
	if ( $av_low + $av_high > 0 ) {
		$lines[] = [ 'key' => 'av', 'label' => 'AV', 'note' => implode( ' + ', $av_notes ), 'low' => $av_low, 'high' => $av_high, 'src' => 'av' ];
		$ind_low  += $av_low;
		$ind_high += $av_high;
	}

	$fac_bands = [
		'half'   => [ 'note' => 'External facilitator · half-day', 'band' => $ind['FAC_HALF'] ],
		'full'   => [ 'note' => 'External facilitator · full-day', 'band' => $ind['FAC_FULL'] ],
		'senior' => [ 'note' => 'Senior / multi-day facilitator',   'band' => $ind['FAC_SENIOR'] ],
	];
	if ( isset( $fac_bands[ $fac ] ) ) {
		$lines[] = [ 'key' => 'facilitation', 'label' => 'Facilitation', 'note' => $fac_bands[ $fac ]['note'], 'low' => $fac_bands[ $fac ]['band'][0], 'high' => $fac_bands[ $fac ]['band'][1], 'src' => 'facilitation' ];
		$ind_low  += $fac_bands[ $fac ]['band'][0];
		$ind_high += $fac_bands[ $fac ]['band'][1];
	}

	$mat_low   = 0;
	$mat_high  = 0;
	$mat_notes = [];
	if ( ! empty( $materials['boards'] ) )  { $mat_low += $ind['MAT_WHITEBOARDS'][0]; $mat_high += $ind['MAT_WHITEBOARDS'][1]; $mat_notes[] = 'whiteboards'; }
	if ( ! empty( $materials['postits'] ) ) { $mat_low += $ind['MAT_POSTITS'][0];     $mat_high += $ind['MAT_POSTITS'][1];     $mat_notes[] = 'post-its+pens'; }
	if ( ! empty( $materials['printing'] ) ){ $mat_low += $ind['MAT_PRINTING'][0];    $mat_high += $ind['MAT_PRINTING'][1];    $mat_notes[] = 'printing'; }
	if ( $mat_low + $mat_high > 0 ) {
		$lines[] = [ 'key' => 'materials', 'label' => 'Materials', 'note' => implode( ' · ', $mat_notes ), 'low' => $mat_low, 'high' => $mat_high ];
		$ind_low  += $mat_low;
		$ind_high += $mat_high;
	}

	if ( 'standard' === $setup ) {
		$lines[] = [ 'key' => 'setup', 'label' => 'Setup + pack-down', 'note' => 'Standard room reset', 'low' => $ind['SETUP_STD'][0], 'high' => $ind['SETUP_STD'][1] ];
		$ind_low  += $ind['SETUP_STD'][0];
		$ind_high += $ind['SETUP_STD'][1];
	} elseif ( 'complex' === $setup ) {
		$lines[] = [ 'key' => 'setup', 'label' => 'Setup + pack-down', 'note' => 'Complex reset / multi-room', 'low' => $ind['SETUP_COMPLEX'][0], 'high' => $ind['SETUP_COMPLEX'][1] ];
		$ind_low  += $ind['SETUP_COMPLEX'][0];
		$ind_high += $ind['SETUP_COMPLEX'][1];
	}

	foreach ( $state['custom'] as $cl ) {
		$lines[] = [ 'key' => 'custom', 'label' => $cl['label'], 'note' => 'Custom line you added', 'low' => $cl['value'], 'high' => $cl['value'] ];
		$ind_low  += $cl['value'];
		$ind_high += $cl['value'];
	}

	// --- TWO/FIFTYSEVEN LINES ---
	$our_lines = [];
	$space_key = $size <= 6 && 'evening' !== $duration ? 'meeting-room' : ( $size <= 12 ? 'studio' : ( $size <= 36 ? 'workshop' : ( $size <= 80 ? 'event' : 'entire' ) ) );

	$room_info = two57_meet_rooms()[ $space_key ] ?? null;
	$room_key  = $room_info ? 'room_' . $room_info['key'] : '';
	$space_name = $room_info['name'] ?? $space_key;
	$room_rate  = 0;
	$room_note  = '';
	$rates = [ 'day' => 0, 'hour' => 0, 'evening' => 0 ];
	if ( $room_key ) {
		$rates['day']     = (float) get_field( $room_key . '_day', 'option' );
		$rates['hour']    = (float) get_field( $room_key . '_hour', 'option' );
		$rates['evening'] = (float) get_field( $room_key . '_evening', 'option' );
	}
	if ( 'hourly' === $duration ) {
		$room_rate = $rates['hour'] * 3;
		$room_note = $space_name . ' · $' . number_format( $rates['hour'] ) . '/hr × 3 hr';
	} elseif ( 'evening' === $duration && $rates['evening'] > 0 ) {
		$room_rate = $rates['evening'];
		$room_note = $space_name . ' · evening rate';
	} elseif ( 'multi-day' === $duration ) {
		$room_rate = $rates['day'] * $multi_days;
		$room_note = $space_name . ' · day rate × ' . $multi_days;
	} elseif ( 'half-day' === $duration ) {
		$room_rate = round( $rates['day'] * 0.6 );
		$room_note = $space_name . ' · half-day (60% of day)';
	} else {
		$room_rate = $rates['day'];
		$room_note = $space_name . ' · day rate';
	}
	$our_lines[] = [ 'key' => 'room', 'label' => 'Room', 'note' => $room_note, 'value' => $room_rate ];
	$ours_total  = $room_rate;

	if ( ! empty( $catering['tea'] ) ) {
		$tea_rate = (float) get_field( 'tea_single_per_head', 'option' );
		if ( $tea_rate <= 0 ) {
			$tea_rate = 5;
		}
		$tea_cost = $tea_rate * $size;
		$our_lines[] = [ 'key' => 'tea', 'label' => 'Tea + coffee', 'note' => '$' . number_format( $tea_rate ) . '/head · continuous', 'value' => $tea_cost ];
		$ours_total  += $tea_cost;
	}

	// Catering — free when the customer arranges it direct, charged at cost
	// when 2/57 arranges. Comparison uses the industry midpoint (NOT the
	// meet-pricing catering_organising_fee quote model).
	$cat_mid = [
		'breakfast'   => ( $ind['BREAKFAST'][0] + $ind['BREAKFAST'][1] ) / 2,
		'lunchLight'  => ( $ind['LUNCH_LIGHT'][0] + $ind['LUNCH_LIGHT'][1] ) / 2,
		'lunchHearty' => ( $ind['LUNCH_HEARTY'][0] + $ind['LUNCH_HEARTY'][1] ) / 2,
		'afternoon'   => ( $ind['AFTERNOON'][0] + $ind['AFTERNOON'][1] ) / 2,
		'drinks'      => ( $ind['DRINKS'][0] + $ind['DRINKS'][1] ) / 2,
	];
	$ours_catering = 0;
	$cat_bits      = [];
	foreach ( $cat_mid as $key => $mid ) {
		if ( empty( $catering[ $key ] ) ) {
			continue;
		}
		$ours_catering += round( $mid * $size );
		$cat_bits[]     = strtolower( $key );
	}
	if ( 'multi-day' === $duration ) {
		$ours_catering *= $multi_days;
	}
	if ( $ours_catering > 0 ) {
		$our_lines[] = [ 'key' => 'catering', 'label' => 'Catering', 'note' => 'Free when you arrange it directly, charged at cost when we arrange it.', 'value' => $ours_catering ];
		$ours_total  += $ours_catering;
	}

	// AV add-ons at the ACF flat maintenance-replacement rate (per booking).
	$av_rate = static function ( float $flat, string $duration, int $multi_days ): float {
		if ( 'hourly' === $duration ) return $flat * 3;
		if ( 'multi-day' === $duration ) return $flat * $multi_days;
		return $flat;
	};
	$av_proj_flat = (float) get_field( 'av_projector_flat', 'option' );
	$av_snd_flat  = (float) get_field( 'av_sound_flat', 'option' );
	if ( $av_proj_flat <= 0 ) $av_proj_flat = 50;
	if ( $av_snd_flat <= 0 )  $av_snd_flat  = 50;
	if ( ! empty( $av['projector'] ) ) {
		$v = $av_rate( $av_proj_flat, $duration, $multi_days );
		$our_lines[] = [ 'key' => 'av-proj', 'label' => 'Projector + screen', 'note' => 'Maintenance-replacement rate · separate add-on', 'value' => $v ];
		$ours_total  += $v;
	}
	if ( ! empty( $av['sound'] ) ) {
		$v = $av_rate( $av_snd_flat, $duration, $multi_days );
		$our_lines[] = [ 'key' => 'av-sound', 'label' => 'Sound system + mic', 'note' => 'Maintenance-replacement rate · separate add-on', 'value' => $v ];
		$ours_total  += $v;
	}

	// Facilitation + materials + setup — pass through at industry mid / ACF charge.
	$mid_of = static function ( array $b ): int {
		return (int) round( ( $b[0] + $b[1] ) / 2 );
	};
	if ( 'half' === $fac ) {
		$v = $mid_of( $ind['FAC_HALF'] );
		$our_lines[] = [ 'key' => 'fac', 'label' => 'Facilitation', 'note' => 'Bring your own facilitator (industry mid shown)', 'value' => $v ];
		$ours_total  += $v;
	}
	if ( 'full' === $fac ) {
		$v = $mid_of( $ind['FAC_FULL'] );
		$our_lines[] = [ 'key' => 'fac', 'label' => 'Facilitation', 'note' => 'Bring your own facilitator (industry mid shown)', 'value' => $v ];
		$ours_total  += $v;
	}
	if ( 'senior' === $fac ) {
		$v = $mid_of( $ind['FAC_SENIOR'] );
		$our_lines[] = [ 'key' => 'fac', 'label' => 'Facilitation', 'note' => 'Bring your own facilitator (industry mid shown)', 'value' => $v ];
		$ours_total  += $v;
	}

	$mat_postits  = (float) get_field( 'materials_postits_charge', 'option' );
	$mat_printing = (float) get_field( 'materials_printing_charge', 'option' );
	if ( $mat_postits <= 0 ) $mat_postits = 30;
	if ( $mat_printing <= 0 ) $mat_printing = 60;
	if ( ! empty( $materials['boards'] ) || ! empty( $materials['postits'] ) || ! empty( $materials['printing'] ) ) {
		$v    = 0;
		$bits = [];
		if ( ! empty( $materials['boards'] ) )  $bits[] = 'whiteboards + flipcharts (included)';
		if ( ! empty( $materials['postits'] ) ) { $v += $mat_postits;  $bits[] = 'post-its + pens'; }
		if ( ! empty( $materials['printing'] ) ){ $v += $mat_printing; $bits[] = 'printing'; }
		$our_lines[] = [ 'key' => 'materials', 'label' => 'Materials', 'note' => implode( ' · ', $bits ), 'value' => $v ];
		$ours_total  += $v;
	}

	if ( 'complex' === $setup ) {
		$complex = (float) get_field( 'setup_complex_charge', 'option' );
		if ( $complex <= 0 ) {
			$complex = 200;
		}
		$our_lines[] = [ 'key' => 'setup', 'label' => 'Complex setup', 'note' => 'Multi-room or non-standard reset', 'value' => $complex ];
		$ours_total  += $complex;
	}

	foreach ( $state['custom'] as $cl ) {
		$our_lines[] = [ 'key' => 'custom', 'label' => $cl['label'], 'note' => 'Custom line you added', 'value' => $cl['value'] ];
		$ours_total  += $cl['value'];
	}

	// Impact Discount — % off the 2/57 total only (never the industry band).
	$impact_amt = 0;
	if ( ! empty( $state['impact'] ) ) {
		$discount_pct = (float) get_field( 'impact_discount_pct', 'option' );
		if ( $discount_pct <= 0 ) {
			$discount_pct = 50;
		}
		$discount_frac = $discount_pct / 100;
		$impact_amt    = round( $ours_total * $discount_frac );
		$our_lines[] = [ 'key' => 'impact', 'label' => 'Impact Discount', 'note' => number_format( $discount_pct, 0 ) . '% off the two/fiftyseven figure', 'value' => -$impact_amt ];
		$ours_total  -= $impact_amt;
	}

	$saving_low  = max( 0, round( $ind_low - $ours_total ) );
	$saving_high = max( 0, round( $ind_high - $ours_total ) );

	return [
		'calc'        => 'meeting-costs',
		'empty'       => false,
		'size'        => $size,
		'duration'    => $duration,
		'hours'       => round( $total_hours * 10 ) / 10,
		'multiDays'   => $multi_days,
		'spaceKey'    => $space_key,
		'spaceName'   => $space_name,
		'industry'    => [ 'low' => (int) round( $ind_low ), 'high' => (int) round( $ind_high ), 'lines' => $lines ],
		'ours'        => [ 'total' => (int) round( $ours_total ), 'lines' => $our_lines ],
		'saving'      => [ 'low' => (int) $saving_low, 'high' => (int) $saving_high ],
		'impactAmt'   => $impact_amt,
	];
}


/**
 * C3 — office-costs recompute. Mirrors the office-costs.js engine's compute()
 * + savings-band math; methodology constants (grade/precinct modifiers,
 * cited defaults) stay in code, membership prices come from the ACF SSOT,
 * never the client. Changes here must be mirrored in
 * assets/js/modules/office-costs.js and vice-versa.
 *
 * @param array $state sanitized { team, days[], grade, precinct, sqmPerPerson, …, bookingSoftware, customLines[] }
 * @return array|WP_Error
 */
function two57_calc_figures_office_costs( array $state ) {
	if ( ! function_exists( 'get_field' ) ) {
		return new WP_Error( 'acf_missing', 'Calculator data store unavailable.' );
	}

	// --- Modifier tables + methodology defaults (cited, stay in code) ---
	$grade_mod = [
		'A-grade'           => 1.35,
		'B-grade fitted'    => 1.00,
		'B-grade unfitted'  => 0.78,
		'C-grade'           => 0.62,
	];
	$precinct_mod = [
		'CBD core'    => 1.15,
		'CBD fringe'  => 1.00,
		'Te Aro'      => 0.92,
		'Thorndon'    => 1.05,
		'Lambton'     => 1.20,
		'Kelburn'     => 0.85,
		'Mt Vic'      => 0.95,
	];
	$weeks_per_yr = 46;

	// Membership prices from the SSOT (same slugs workspace-pricing uses).
	$price = static function ( string $slug ): float {
		$p = (float) get_field( 'membership_' . $slug . '_monthly', 'option' );
		return $p > 0 ? $p : 0;
	};
	$flexi     = [ 1 => $price( 'flexi_1' ), 2 => $price( 'flexi_2' ), 3 => $price( 'flexi_3' ), 4 => $price( 'flexi_4' ), 5 => $price( 'flexi_5' ) ];
	$dedicated = $price( 'dedicated' );

	$team = (int) $state['team'];

	if ( $team <= 0 ) {
		return [
			'calc'           => 'office-costs',
			'empty'          => true,
			'team'           => 0,
			'annualTotal'    => 0,
			'monthlyTotal'   => 0,
			'perPersonMonth' => 0,
			'perPersonDay'   => 0,
			'perSqmYr'       => 0,
			'sqmTotal'       => 0,
			'lines'          => [],
			'categories'     => [],
			'valueAdd'       => [ 'livingWage' => 0, 'carbon' => 0, 'climatePower' => 0, 'giving' => 0, 'mhfr' => 0, 'total' => 0 ],
			'saving'         => [ 'low' => 0, 'high' => 0, 'active' => false ],
		];
	}

	$days      = array_values( $state['days'] );
	$avg_days  = array_sum( $days ) / count( $days );
	$sqm_pp    = (float) $state['sqmPerPerson'];
	$rent_sqm  = (float) $state['rentPerSqmPerYr'];
	$opex_pct  = (float) $state['outgoingsPctOfRent'];
	$sqm_total = $team * $sqm_pp;

	$gm = $grade_mod[ $state['grade'] ] ?? 1.0;
	$pm = $precinct_mod[ $state['precinct'] ] ?? 1.0;

	$rent      = $sqm_total * $rent_sqm * $gm * $pm;
	$outgoings = $rent * $opex_pct;
	$furniture = (float) $state['furnitureAmortYrs'] > 0 ? ( $team * (float) $state['furniturePerPerson'] ) / (float) $state['furnitureAmortYrs'] : 0;
	$internet  = (float) $state['internetPerMo'] * 12;
	$power     = ( $sqm_total * (float) $state['powerWattsPerSqm'] * (float) $state['powerHoursPerYear'] * (float) $state['powerPricePerKwh'] ) / 1000;
	$cleaning  = $sqm_total * (float) $state['cleaningHoursPerSqmYr'] * (float) $state['cleaningPerHour'];
	$kb        = $team * (float) $state['kbPerPersonPerYr'];
	$insurance = $team * (float) $state['insurancePerPersonPerYr'];
	$first_aid = $team * (float) $state['firstAidPerPersonPerYr'];
	$fire      = $team * (float) $state['fireWardenPerPersonPerYr'];
	$admin     = $team * (float) $state['powerHoursPerYear'] * (float) $state['adminPctOfHours'] * (float) $state['adminLoadedHourly'];
	$legals    = (float) $state['leaseTermYears'] > 0 ? (float) $state['leaseLegalsOneOff'] / (float) $state['leaseTermYears'] : 0;

	$booking_active = ! empty( $state['bookingSoftware'] ) || $team >= 10;
	$booking        = $booking_active ? $team * (float) $state['bookingSoftwareCost'] * 12 : 0;

	$custom_sum = 0;
	foreach ( $state['customLines'] as $cl ) {
		$custom_sum += (float) $cl['value'];
	}

	$annual_total = $rent + $outgoings + $furniture + $internet + $power + $cleaning
		+ $kb + $insurance + $first_aid + $fire + $admin + $legals + $booking + $custom_sum;

	$working_days = $avg_days * $weeks_per_yr;
	$per_pp_day   = $working_days > 0 ? $annual_total / $team / $working_days : 0;

	// --- Line list (label + value + cited note) ---
	$lines = [
		[ 'label' => 'Rent', 'value' => $rent, 'note' => '$' . round( $rent_sqm ) . '/m²/yr × ' . $sqm_pp . ' m²/pp × ' . $team . ' people × ' . number_format( $gm, 2 ) . ' (' . $state['grade'] . ') × ' . number_format( $pm, 2 ) . ' (' . $state['precinct'] . ')' ],
		[ 'label' => 'Outgoings', 'value' => $outgoings, 'note' => round( $opex_pct * 100 ) . '% of rent' ],
		[ 'label' => 'Furniture (amortised)', 'value' => $furniture, 'note' => '$' . round( (float) $state['furniturePerPerson'] ) . '/pp × ' . $team . ' ÷ ' . (float) $state['furnitureAmortYrs'] . ' yrs' ],
		[ 'label' => 'Internet', 'value' => $internet, 'note' => '$' . round( (float) $state['internetPerMo'] ) . '/mo business fibre × 12' ],
		[ 'label' => 'Power', 'value' => $power, 'note' => (float) $state['powerWattsPerSqm'] . ' W/m² × ' . $sqm_total . ' m² × ' . (float) $state['powerHoursPerYear'] . ' hrs × $' . number_format( (float) $state['powerPricePerKwh'], 2 ) . '/kWh' ],
		[ 'label' => 'Cleaning', 'value' => $cleaning, 'note' => (float) $state['cleaningHoursPerSqmYr'] . ' hr/m²/yr × $' . round( (float) $state['cleaningPerHour'] ) . '/hr × ' . $sqm_total . ' m²' ],
		[ 'label' => 'Kitchen + bathroom', 'value' => $kb, 'note' => '$' . round( (float) $state['kbPerPersonPerYr'] ) . '/pp/yr consumables' ],
		[ 'label' => 'Insurance', 'value' => $insurance, 'note' => '$' . round( (float) $state['insurancePerPersonPerYr'] ) . '/pp/yr combined' ],
		[ 'label' => 'First aid training', 'value' => $first_aid, 'note' => '$' . round( (float) $state['firstAidPerPersonPerYr'] ) . '/pp/yr (H&S Act 2015 compliance)' ],
		[ 'label' => 'Fire warden training', 'value' => $fire, 'note' => '$' . round( (float) $state['fireWardenPerPersonPerYr'] ) . '/pp/yr (FENZ requirement)' ],
		[ 'label' => 'Admin time', 'value' => $admin, 'note' => round( (float) $state['adminPctOfHours'] * 100 ) . '% of team hours × $' . round( (float) $state['adminLoadedHourly'] ) . '/hr loaded' ],
		[ 'label' => 'Lease legals (amortised)', 'value' => $legals, 'note' => '$' . number_format( round( (float) $state['leaseLegalsOneOff'] ) ) . ' one-off ÷ ' . (float) $state['leaseTermYears'] . ' yr term' ],
	];
	if ( $booking_active ) {
		$lines[] = [ 'label' => 'Booking software', 'value' => $booking, 'note' => '$' . (float) $state['bookingSoftwareCost'] . '/pp/mo × ' . $team . ' × 12 (auto-on at team ≥ 10)' ];
	}
	foreach ( $state['customLines'] as $cl ) {
		$lines[] = [ 'label' => $cl['label'], 'value' => (float) $cl['value'], 'note' => 'Custom line you added' ];
	}

	$categories = [
		'rent-opex'              => $rent + $outgoings,
		'utilities'              => $internet + $power,
		'cleaning-kb'            => $cleaning + $kb,
		'compliance-insurance'   => $insurance + $first_aid + $fire,
		'furniture-admin-legals' => $furniture + $admin + $legals,
		'addons-custom'          => $booking + $custom_sum,
	];

	// Value-add quantification (Job 11).
	$value_add = [
		'livingWage'   => 7.92 * $sqm_total,
		'carbon'       => 1.25 * $team,
		'climatePower' => $power * 0.05,
		'giving'       => $team * (float) $state['powerHoursPerYear'],
		'mhfr'         => ( 445 * ceil( $team / 12 ) ) / 2.5,
	];
	$value_add['total'] = array_sum( $value_add );

	// Savings band — per-member days → Flexi tier, 5 days = Dedicated high.
	$saving_low  = 0;
	$saving_high = 0;
	foreach ( $days as $d ) {
		$d = two57_calc_int( $d, 1, 5 );
		$saving_low  += $flexi[ $d ] ?? 0;
		$saving_high += ( 5 === $d ) ? $dedicated : ( $flexi[ $d ] ?? 0 );
	}
	$saving_low  *= 12;
	$saving_high *= 12;
	$save_floor = $annual_total - $saving_high;
	$save_ceil  = $annual_total - $saving_low;
	$saving_active = $annual_total > 0 && $save_floor > 0;

	return [
		'calc'           => 'office-costs',
		'empty'          => false,
		'team'           => $team,
		'grade'          => $state['grade'],
		'precinct'       => $state['precinct'],
		'annualTotal'    => (int) round( $annual_total ),
		'monthlyTotal'   => (int) round( $annual_total / 12 ),
		'perPersonMonth' => (int) round( $annual_total / $team / 12 ),
		'perPersonDay'   => (int) round( $per_pp_day ),
		'perSqmYr'       => (int) round( $sqm_total > 0 ? $annual_total / $sqm_total : 0 ),
		'sqmTotal'       => (int) round( $sqm_total ),
		'lines'          => $lines,
		'categories'     => $categories,
		'valueAdd'       => array_map( 'round', $value_add ),
		'saving'         => [ 'low' => $save_ceil, 'high' => $save_floor, 'active' => $saving_active ],
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
			$compose = two57_calc_compose_hours_to_impact( $figures, $state, $page, $to );
			break;
		case 'workspace-pricing':
			$compose = two57_calc_compose_workspace_pricing( $figures, $state, $page, $to );
			break;
		case 'meet-pricing':
			$compose = two57_calc_compose_meet_pricing( $figures, $state, $page, $to );
			break;
		case 'office-carbon':
			$compose = two57_calc_compose_office_carbon( $figures, $state, $page, $to );
			break;
		case 'meeting-costs':
			$compose = two57_calc_compose_meeting_costs( $figures, $state, $page, $to );
			break;
		case 'office-costs':
			$compose = two57_calc_compose_office_costs( $figures, $state, $page, $to );
			break;
		default:
			return [];
	}

	return two57_calc_email_letter_open( $compose );
}


/**
 * Shared letter wrapper — a te reo greeting + sign-off applied to every
 * calculator email, inserted around the body and just before the address /
 * contact-policy imprint. Applied centrally here so all current + future
 * calcs get it without per-calc copy.
 *
 * @param array $compose { subject, summary, plain, html }
 * @return array
 */
function two57_calc_email_letter_open( array $compose ): array {
	$greeting = 'Kia ora.' . "\n\n" . 'Ka pai for running your numbers at two/fiftyseven!';
	$signoff  = 'One of our friendly kaitiaki will be in touch to follow up if you need further assistance.' . "\n" . 'Ngā mihi nui.';

	// --- Plain text: greeting on top, sign-off just before the '—' imprint.
	$plain  = $greeting . "\n\n" . $compose['plain'];
	$marker = "\n\n—\n";
	$pos    = strpos( $plain, $marker );
	if ( false !== $pos ) {
		$plain = substr( $plain, 0, $pos ) . "\n\n" . $signoff . substr( $plain, $pos );
	} else {
		$plain .= "\n\n" . $signoff;
	}

	// --- HTML: greeting paragraph on top, sign-off before the footer imprint.
	$style      = 'font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;';
	$greet_html = '<p style="' . $style . '">Kia ora.<br>Ka pai for running your numbers at two/fiftyseven!</p>';
	$sign_html  = '<p style="' . $style . '">' . str_replace( "\n", '<br>', $signoff ) . '</p>';

	$html = $greet_html . $compose['html'];
	$foot = strrpos( $html, '<p style' );
	if ( false !== $foot ) {
		$html = substr( $html, 0, $foot ) . $sign_html . substr( $html, $foot );
	}

	$compose['plain'] = $plain;
	$compose['html']  = $html;
	return $compose;
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
 * C5 — office-carbon email copy. Mirrors the engine's fmtKg rounding: kg
 * below 10,000, one-decimal tonnes at/above it, and '0 t' on a zero total.
 */
function two57_calc_compose_office_carbon( array $figures, array $state, string $page, string $to ): array {
	$kg = static function ( float $n ): string {
		$rounded = round( $n );
		if ( $rounded === 0.0 ) return '0 t';
		if ( abs( $rounded ) >= 10000 ) {
			return number_format( $rounded / 1000, 1 ) . ' tCO₂e';
		}
		return number_format( $rounded ) . ' kgCO₂e';
	};
	$kg_signed = static function ( float $n ) use ( $kg ): string {
		return ( $n < 0 ? '−' : '' ) . $kg( abs( $n ) );
	};

	$summary = sprintf(
		'For a team of %d, %d days a week, %d weeks a year at %s hours a day: running your own office is about %s a year; at two/fiftyseven the measured share is %s and, with the 200%% verified offset, your net position is carbon-positive at %s.',
		$figures['team'],
		$figures['daysPerWeek'],
		$figures['weeksPerYear'],
		$figures['hoursPerDay'],
		$kg( $figures['privateTotal'] ),
		$kg( $figures['oursTotal'] ),
		$kg( $figures['oursPositive'] )
	);

	$link = home_url( $page );
	$link = add_query_arg( [
		'team'  => $figures['team'],
		'days'  => $figures['daysPerWeek'],
		'weeks' => $figures['weeksPerYear'],
		'hours' => $figures['hoursPerDay'],
	], $link );

	$subject = 'Your two/fiftyseven carbon calculation';

	$plain = implode( "\n\n", [
		$summary,
		'Your inputs: ' . $figures['team'] . ' people · ' . $figures['daysPerWeek'] . ' days/wk · ' . $figures['weeksPerYear'] . ' weeks/yr at ' . $figures['hoursPerDay'] . ' h/day',
		'Private NZ office baseline (operational): ' . $kg( $figures['privateTotal'] ),
		'Measured at two/fiftyseven (Tadpole ACE 2025): ' . $kg( $figures['oursTotal'] ),
		'Net position after 200% offset: ' . $kg_signed( $figures['oursOffset'] ),
		'That is ' . $kg( $figures['savedVsPrivate'] ) . ' ahead of a private office, with ' . $kg( $figures['netAvoided'] ) . ' of expected emissions avoided or offset in total.',
		'Methodology: Tadpole ACE 2025 emission factors; Ekos NZUs + Ecotricity Toitū climate positive electricity at 125% (≈200% combined). Biodiversity credits are a separate stream, never aggregated.',
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
			'<strong style="color:#111827;">' . esc_html( $kg( $figures['privateTotal'] ) ) . '</strong> — running your own office, per year<br>',
			'<strong style="color:#111827;">' . esc_html( $kg( $figures['oursTotal'] ) ) . '</strong> — measured at two/fiftyseven<br>',
			'<strong style="color:#111827;">' . esc_html( $kg_signed( $figures['oursOffset'] ) ) . '</strong> — carbon-positive for your share, after the 200% offset',
		'</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;">',
			'<a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open and share your calculation →</a>',
		'</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">',
			'Tadpole ACE 2025 methodology · Ekos NZUs + Ecotricity 125%. Biodiversity credits via Sanctuary Mountain Maungatautari are separate and never aggregated.<br>',
			'two/fiftyseven, Wellington · <a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a>',
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
 * C4 — meeting-costs email copy.
 *
 * @param array  $figures Re-rendered by two57_calc_figures_meeting_costs().
 * @param array  $state   Sanitized by two57_calc_sanitize_meeting_costs().
 * @param string $page    Pathname the calc sits on.
 * @param string $to      Recipient email.
 * @return array { subject, summary, plain, html }
 */
function two57_calc_compose_meeting_costs( array $figures, array $state, string $page, string $to ): array {
	$money = static function ( float $n ): string {
		return '$' . number_format( round( $n ) );
	};
	$band = static function ( float $low, float $high ) use ( $money ): string {
		$low  = round( $low );
		$high = round( $high );
		if ( $low === $high ) {
			return $money( $low );
		}
		return $money( $low ) . ' – ' . $money( $high );
	};
	$duration_word = [
		'hourly'    => 'an hourly',
		'half-day'  => 'a half-day',
		'full-day'  => 'a full-day',
		'evening'   => 'an evening',
		'multi-day' => 'a ' . $figures['multiDays'] . '-day',
	];
	$dur_word = $duration_word[ $figures['duration'] ?? '' ] ?? 'a';

	$empty_email = $figures['empty'] ?? false;

	if ( $empty_email || $figures['size'] <= 0 || ! $figures['duration'] ) {
		$summary = 'Add a group size and each day\'s start + end time and two/fiftyseven will show you the industry-vs-our comparison.';
		$subject = 'Your two/fiftyseven meeting cost comparison';

		$link = home_url( $page );

		$plain = implode( "\n\n", [
			$summary,
			'Open the calculator and rebuild your comparison: ' . $link,
			'',
			'—',
			'two/fiftyseven, Wellington · https://twofiftyseven.co/',
			'Contact policy: ' . home_url( '/contact-policy/' ),
		] );

		$html = implode( '', [
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;"><a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open your comparison →</a></p>',
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br><a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a></p>',
		] );

		return [ 'subject' => $subject, 'summary' => $summary, 'plain' => $plain, 'html' => $html ];
	}

	$size    = $figures['size'];
	$space   = $figures['spaceName'];
	$hours   = $figures['hours'];
	$days    = $figures['multiDays'];
	$ind_low = $figures['industry']['low'];
	$ind_high = $figures['industry']['high'];
	$ours    = $figures['ours']['total'];
	$save_low = $figures['saving']['low'];
	$save_high = $figures['saving']['high'];

	$summary = sprintf(
		'%s booking in the %s for %d %s — a comparable Wellington venue runs %s, at two/fiftyseven it\'s %s, saving %s (excl. GST).',
		ucfirst( $dur_word ),
		$space,
		$size,
		1 === $size ? 'person' : 'people',
		$band( $ind_low, $ind_high ),
		$money( $ours ),
		$band( $save_low, $save_high )
	);

	// Rebuild the share link params (mirror meeting-costs.js writeURL).
	$link_params = [ 'size' => $size ];
	$days_enc = [];
	foreach ( $state['days'] as $day ) {
		$date = isset( $day['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day['date'] ) ? $day['date'] : gmdate( 'Y-m-d' );
		$days_enc[] = $date . '|' . $day['start'] . '-' . $day['end'];
	}
	$link_params['days'] = implode( ',', $days_enc );

	$tokens = [];
	$cat_tokens = [
		'tea'         => 'tea',
		'breakfast'   => 'breakfast',
		'lunchLight'  => 'lunch-light',
		'lunchHearty' => 'lunch-hearty',
		'afternoon'   => 'afternoon',
		'drinks'      => 'drinks',
	];
	foreach ( $cat_tokens as $state_key => $token ) {
		if ( ! empty( $state['catering'][ $state_key ] ) ) {
			$tokens[] = $token;
		}
	}
	$av_tokens = [ 'projector', 'sound' ];
	foreach ( $av_tokens as $token ) {
		if ( ! empty( $state['av'][ $token ] ) ) {
			$tokens[] = $token;
		}
	}
	$mat_tokens = [ 'boards', 'postits', 'printing' ];
	foreach ( $mat_tokens as $token ) {
		if ( ! empty( $state['materials'][ $token ] ) ) {
			$tokens[] = $token;
		}
	}
	if ( ! empty( $state['impact'] ) ) {
		$tokens[] = 'impact-discount';
	}
	if ( $tokens ) {
		$link_params['extras'] = implode( ',', $tokens );
	}
	if ( $state['fac'] && 'none' !== $state['fac'] ) {
		$link_params['fac'] = $state['fac'];
	}
	if ( $state['setup'] && 'standard' !== $state['setup'] ) {
		$link_params['setup'] = $state['setup'];
	}
	if ( $state['custom'] ) {
		$custom_enc = [];
		foreach ( $state['custom'] as $cl ) {
			$custom_enc[] = rawurlencode( $cl['label'] ) . '|' . $cl['value'];
		}
		$link_params['custom'] = implode( ',', $custom_enc );
	}

	$link = add_query_arg( $link_params, home_url( $page ) );

	$subject = 'Your two/fiftyseven meeting cost comparison';

	$ind_lines_plain = [];
	$ind_lines_html  = [];
	foreach ( $figures['industry']['lines'] as $line ) {
		$ind_lines_plain[] = $line['label'] . ' (' . $line['note'] . '): ' . $band( $line['low'], $line['high'] );
		$ind_lines_html[]  = sprintf(
			'%s — <strong style="color:#111827;">%s</strong><br><span style="color:#6b7280;">%s</span>',
			esc_html( $line['label'] ),
			esc_html( $band( $line['low'], $line['high'] ) ),
			esc_html( $line['note'] )
		);
	}

	$ours_lines_plain = [];
	$ours_lines_html  = [];
	foreach ( $figures['ours']['lines'] as $line ) {
		$value = $line['value'];
		$sign  = $value < 0 ? '-' : '';
		$ours_lines_plain[] = $line['label'] . ' (' . $line['note'] . '): ' . $sign . $money( abs( (float) $value ) );
		$ours_lines_html[]  = sprintf(
			'%s — <strong style="color:#111827;">%s%s</strong><br><span style="color:#6b7280;">%s</span>',
			esc_html( $line['label'] ),
			$sign,
			esc_html( $money( abs( (float) $value ) ) ),
			esc_html( $line['note'] )
		);
	}

	$plain = implode( "\n\n", [
		$summary,
		'Duration: ' . $dur_word . ' booking · ' . $hours . ' hour' . ( $hours === 1 ? '' : 's' ) . ( $days > 1 ? ' across ' . $days . ' days' : '' ) . ' · in the ' . $space,
		'',
		'Current local pricing (band):',
		implode( "\n", $ind_lines_plain ),
		'Total · current local pricing: ' . $band( $ind_low, $ind_high ),
		'',
		'At two/fiftyseven:',
		implode( "\n", $ours_lines_plain ),
		'Total at two/fiftyseven: ' . $money( $ours ),
		'',
		'You\'d save: ' . $band( $save_low, $save_high ),
		'',
		'Open or share this comparison: ' . $link,
		'',
		'—',
		'two/fiftyseven, Wellington · https://twofiftyseven.co/',
		'Contact policy: ' . home_url( '/contact-policy/' ),
	] );

	$html = implode( '', [
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;color:#111827;font-weight:600;">' . esc_html( $money( $ours ) ) . ' <span style="font-size:14px;font-weight:400;color:#6b7280;">at two/fiftyseven · excl. GST</span></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">Duration: ' . esc_html( $dur_word . ' booking · ' . $hours . ' hour' . ( $hours === 1 ? '' : 's' ) . ( $days > 1 ? ' across ' . $days . ' days' : '' ) . ' · in the ' . $space ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">Current local pricing:<br>' . implode( '<br>', $ind_lines_html ) . '<br><strong style="color:#111827;">Total · ' . esc_html( $band( $ind_low, $ind_high ) ) . '</strong></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">At two/fiftyseven:<br>' . implode( '<br>', $ours_lines_html ) . '<br><strong style="color:#111827;">Total · ' . esc_html( $money( $ours ) ) . '</strong></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#111827;font-weight:600;">You\'d save: ' . esc_html( $band( $save_low, $save_high ) ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;"><a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open and share your comparison →</a></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br><a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a></p>',
	] );

	return [ 'subject' => $subject, 'summary' => $summary, 'plain' => $plain, 'html' => $html ];
}


/**
 * C3 — office-costs email copy.
 *
 * @param array  $figures Re-rendered by two57_calc_figures_office_costs().
 * @param array  $state   Sanitized by two57_calc_sanitize_office_costs().
 * @param string $page    Pathname the calc sits on.
 * @param string $to      Recipient email.
 * @return array { subject, summary, plain, html }
 */
function two57_calc_compose_office_costs( array $figures, array $state, string $page, string $to ): array {
	$money = static function ( float $n ): string {
		return '$' . number_format( round( $n ) );
	};

	$empty_email = $figures['empty'] ?? false;
	if ( $empty_email || $figures['team'] <= 0 ) {
		$summary = 'Pick a team size and configure your office line-by-line and two/fiftyseven will show you the annual budget — and what your same team costs here.';
		$subject = 'Your two/fiftyseven office cost budget';

		$link = home_url( $page );

		$plain = implode( "\n\n", [
			$summary,
			'Open the calculator and build your budget: ' . $link,
			'',
			'—',
			'two/fiftyseven, Wellington · https://twofiftyseven.co/',
			'Contact policy: ' . home_url( '/contact-policy/' ),
		] );

		$html = implode( '', [
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;"><a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open your budget →</a></p>',
			'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br><a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a></p>',
		] );

		return [ 'subject' => $subject, 'summary' => $summary, 'plain' => $plain, 'html' => $html ];
	}

	$team      = $figures['team'];
	$grade     = $figures['grade'];
	$precinct  = $figures['precinct'];
	$annual    = $figures['annualTotal'];
	$per_month = $figures['monthlyTotal'];
	$per_pp    = $figures['perPersonMonth'];
	$per_day   = $figures['perPersonDay'];
	$per_sqm   = $figures['perSqmYr'];
	$save_low  = $figures['saving']['low'];
	$save_high = $figures['saving']['high'];
	$saving_active = $figures['saving']['active'];
	$save_band = $saving_active ? ( $save_low === $save_high ? $money( $save_low ) : $money( $save_low ) . ' – ' . $money( $save_high ) ) : '';

	$summary = sprintf(
		'Your %d-person team in a %s %s office runs %s a year (%s/mo, %s/pp/day).',
		$team,
		$grade,
		$precinct,
		$money( $annual ),
		$money( $per_month ),
		$money( $per_day )
	);
	if ( $saving_active ) {
		$summary .= ' ' . sprintf( 'The same team at two/fiftyseven saves %s a year.', $save_band );
	}

	// Rebuild the share link params (mirror office-costs.js writeURL compact keys).
	$link_params = [ 'team' => $team ];
	$days_enc = [];
	foreach ( array_slice( $state['days'], 0, $team ) as $d ) {
		$days_enc[] = (int) $d;
	}
	if ( $days_enc ) {
		$link_params['days'] = implode( ',', $days_enc );
	}
	if ( 'B-grade fitted' !== $state['grade'] ) {
		$link_params['grade'] = $state['grade'];
	}
	if ( 'CBD core' !== $state['precinct'] ) {
		$link_params['pre'] = $state['precinct'];
	}
	if ( ! empty( $state['bookingSoftware'] ) ) {
		$link_params['bt'] = '1';
	}

	// Remaining URL-fields that differ from the block defaults.
	$url_fields = [
		'sqmPerPerson'        => 'sqm',
		'rentPerSqmPerYr'     => 'rent',
		'outgoingsPctOfRent'  => 'opex',
		'internetPerMo'       => 'net',
		'powerWattsPerSqm'    => 'pw',
		'powerHoursPerYear'   => 'phr',
		'powerPricePerKwh'    => 'pkw',
		'cleaningHoursPerSqmYr' => 'chrs',
		'cleaningPerHour'     => 'crt',
		'kbPerPersonPerYr'    => 'kb',
		'insurancePerPersonPerYr' => 'ins',
		'firstAidPerPersonPerYr'  => 'fa',
		'fireWardenPerPersonPerYr'=> 'fw',
		'adminPctOfHours'     => 'adp',
		'adminLoadedHourly'   => 'adr',
		'leaseLegalsOneOff'   => 'leg',
		'leaseTermYears'      => 'lty',
		'furniturePerPerson'  => 'fpp',
		'furnitureAmortYrs'   => 'fy',
		'bookingSoftwareCost' => 'bc',
	];
	$defaults = [
		'sqmPerPerson'        => 9,
		'rentPerSqmPerYr'     => 310,
		'outgoingsPctOfRent'  => 0.27,
		'internetPerMo'       => 200,
		'powerWattsPerSqm'    => 50,
		'powerHoursPerYear'   => 1840,
		'powerPricePerKwh'    => 0.30,
		'cleaningHoursPerSqmYr' => 1.2,
		'cleaningPerHour'     => 45,
		'kbPerPersonPerYr'    => 300,
		'insurancePerPersonPerYr' => 200,
		'firstAidPerPersonPerYr'  => 28,
		'fireWardenPerPersonPerYr'=> 18,
		'adminPctOfHours'     => 0.06,
		'adminLoadedHourly'   => 70,
		'leaseLegalsOneOff'   => 3500,
		'leaseTermYears'      => 3,
		'furniturePerPerson'  => 2000,
		'furnitureAmortYrs'   => 5,
		'bookingSoftwareCost' => 8,
	];
	foreach ( $url_fields as $state_key => $token ) {
		if ( ! isset( $state[ $state_key ] ) ) continue;
		if ( abs( (float) $state[ $state_key ] - $defaults[ $state_key ] ) < 0.0001 ) continue;
		$link_params[ $token ] = $state[ $state_key ];
	}
	// Custom lines — the engine's c{i}l / c{i}v compact URL keys.
	$i = 0;
	foreach ( $state['customLines'] as $cl ) {
		$link_params[ 'c' . $i . 'l' ] = $cl['label'];
		$link_params[ 'c' . $i . 'v' ] = $cl['value'];
		$i++;
	}

	$link = add_query_arg( $link_params, home_url( $page ) );

	$subject = 'Your two/fiftyseven office cost budget';

	// --- Line rendering ---
	$lines_plain = [];
	$lines_html  = [];
	foreach ( $figures['lines'] as $line ) {
		$lines_plain[] = $line['label'] . ' (' . $line['note'] . '): ' . $money( (float) $line['value'] );
		$lines_html[]  = sprintf(
			'%s — <strong style="color:#111827;">%s</strong><br><span style="color:#6b7280;">%s</span>',
			esc_html( $line['label'] ),
			esc_html( $money( (float) $line['value'] ) ),
			esc_html( $line['note'] )
		);
	}

	// --- Category share (mirror the engine's category labels) ---
	$cat_labels = [
		'rent-opex'              => 'Rent + outgoings',
		'utilities'              => 'Utilities',
		'cleaning-kb'            => 'Cleaning + consumables',
		'compliance-insurance'   => 'Compliance + insurance',
		'furniture-admin-legals' => 'Furniture + admin + legals',
		'addons-custom'          => 'Add-ons + custom',
	];
	$cat_plain = [];
	$cat_html  = [];
	foreach ( $cat_labels as $key => $label ) {
		$value = $figures['categories'][ $key ] ?? 0;
		$pct   = $annual > 0 ? round( ( $value / $annual ) * 100 ) : 0;
		$cat_plain[] = $label . ': ' . $money( (float) $value ) . ' (' . $pct . '%)';
		$cat_html[]  = $label . ' — <strong style="color:#111827;">' . esc_html( $money( (float) $value ) ) . '</strong> <span style="color:#6b7280;">(' . $pct . '% of total)</span>';
	}

	// --- Value-add quantification (Job 11) ---
	$va_plain = [];
	$va_html  = [];
	foreach ( $figures['valueAdd'] as $key => $value ) {
		if ( 'total' === $key ) continue;
		$label = [
			'livingWage'   => 'Living-wage cleaners',
			'carbon'       => 'Verified carbon offset',
			'climatePower' => 'Climate-positive power',
			'giving'       => 'Giving contribution',
			'mhfr'         => 'MHFR-trained team',
		][ $key ];
		$va_plain[] = $label . ': ' . $money( (float) $value );
		$va_html[]  = $label . ' — <strong style="color:#111827;">' . esc_html( $money( (float) $value ) ) . '</strong>';
	}

	$plain = implode( "\n\n", [
		$summary,
		$team . ' people · ' . $grade . ' · ' . $precinct,
		'',
		'Budget:',
		implode( "\n", $lines_plain ),
		'Annual total: ' . $money( $annual ),
		'',
		'How it splits by category:',
		implode( "\n", $cat_plain ),
		'',
		'Per month: ' . $money( $per_month ) . ' · per person/mo: ' . $money( $per_pp ) . ' · per person/day: ' . $money( $per_day ) . ' · per m²/yr: ' . $money( $per_sqm ),
	] );
	if ( $saving_active ) {
		$plain .= "\n\n" . 'The same team at two/fiftyseven saves: ' . $save_band;
	}
	$plain .= implode( "\n\n", [
		'',
		'What your office quietly funds:',
		implode( "\n", $va_plain ),
		'Equivalent procured-separately value: ' . $money( (float) $figures['valueAdd']['total'] ),
		'',
		'Open or share this budget: ' . $link,
		'',
		'—',
		'two/fiftyseven, Wellington · https://twofiftyseven.co/',
		'Contact policy: ' . home_url( '/contact-policy/' ),
	] );

	$html = implode( '', [
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#1f2937;">' . esc_html( $summary ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;color:#111827;font-weight:600;">' . esc_html( $money( $annual ) ) . ' <span style="font-size:14px;font-weight:400;color:#6b7280;">a year · your office</span></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">' . esc_html( $team . ' people · ' . $grade . ' · ' . $precinct ) . '<br>' . esc_html( 'Per month: ' . $money( $per_month ) . ' · per person/mo: ' . $money( $per_pp ) . ' · per person/day: ' . $money( $per_day ) . ' · per m²/yr: ' . $money( $per_sqm ) ) . '</p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">Budget:<br>' . implode( '<br>', $lines_html ) . '<br><strong style="color:#111827;">Annual total · ' . esc_html( $money( $annual ) ) . '</strong></p>',
		'<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">How it splits by category:<br>' . implode( '<br>', $cat_html ) . '</p>',
	] );
	if ( $saving_active ) {
		$html .= '<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;color:#111827;font-weight:600;">The same team at two/fiftyseven saves: ' . esc_html( $save_band ) . ' a year</p>';
	}
	$html .= '<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#374151;">What your office quietly funds:<br>' . implode( '<br>', $va_html ) . '<br><strong style="color:#111827;">Equivalent procured-separately value · ' . esc_html( $money( (float) $figures['valueAdd']['total'] ) ) . '</strong></p>';
	$html .= '<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:16px;line-height:1.6;"><a href="' . esc_url( $link ) . '" style="color:#2563eb;font-weight:600;">Open and share your budget →</a></p>';
	$html .= '<p style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">two/fiftyseven, Wellington.<br><a href="' . esc_url( home_url( '/contact-policy/' ) ) . '" style="color:#6b7280;">Contact policy</a></p>';

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