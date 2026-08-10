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
	}

	return new WP_Error( 'unsupported_calc', 'Unsupported calculator.' );
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