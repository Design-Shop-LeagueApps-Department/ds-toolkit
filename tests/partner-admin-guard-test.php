<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a command-line test: does nothing over HTTP
/**
 * Administrator accounts and the staff rule (1.12.0).
 *
 *   wp eval-file tests/partner-admin-guard-test.php
 *
 * Creates temporary users (a Partner, two administrators, a subscriber), deletes them at the end, and puts
 * default_role back. Needs the Partner role (User Roles feature, on by default).
 */
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0; $n = 0;
$is = function ( $label, $got, $want ) use ( &$fails, &$n ) { $n++; $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) ) . "\n"; };
if ( ! get_role( 'partner' ) ) { echo "SKIP: no Partner role on this site\n"; return; }
$r      = wp_rand();
$mk     = function ( $role, $email ) use ( $r ) { return wp_insert_user( array( 'user_login' => 'guard_' . $role . '_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => $email, 'role' => $role ) ); };
$admin  = $mk( 'administrator', "guard-admin-$r@leagueapps.com" );
$admin2 = $mk( 'administrator', "guard-owner-$r@club.example" );
$part   = $mk( 'partner', "guard-partner-$r@club.example" );
$sub    = $mk( 'subscriber', "guard-sub-$r@club.example" );
$made   = array();

wp_set_current_user( $part );
$is( 'partner: Administrator is not in the roles they can give', isset( get_editable_roles()['administrator'] ), false );
$is( 'partner: Partner and the other roles still are', isset( get_editable_roles()['partner'], get_editable_roles()['editor'] ), true );
$is( 'partner: cannot edit an administrator (email, password)', current_user_can( 'edit_user', $admin2 ), false );
$is( 'partner: cannot delete an administrator', current_user_can( 'delete_user', $admin2 ), false );
$is( 'partner: cannot change an administrator\'s role', current_user_can( 'promote_user', $admin2 ), false );
$is( 'partner: still manages their own staff (edit a subscriber)', current_user_can( 'edit_user', $sub ), true );
$is( 'partner: still edits themselves', current_user_can( 'edit_user', $part ), true );
$req = new WP_REST_Request( 'POST', '/wp/v2/users' );
$req->set_body_params( array( 'username' => 'guard_rest_' . $r, 'email' => "guard-rest-$r@club.example", 'password' => wp_generate_password(), 'roles' => array( 'administrator' ) ) );
$res = rest_do_request( $req );
if ( ! $res->is_error() ) { $made[] = (int) ( $res->get_data()['id'] ?? 0 ); }
$is( 'partner: creating an Administrator over the REST API is refused', $res->is_error(), true );
$before = get_option( 'default_role' );
update_option( 'default_role', 'administrator' );
$is( 'partner: New User Default Role cannot become Administrator', get_option( 'default_role' ), $before );
wp_update_user( array( 'ID' => $part, 'user_email' => "guard-partner-$r@leagueapps.com" ) );
wp_set_current_user( $part );
$is( 'partner with an @leagueapps.com address is not staff', DS_Toolkit::is_leagueapps_user(), false );

wp_set_current_user( $admin );
$is( 'administrator with a staff address is staff', DS_Toolkit::is_leagueapps_user(), true );
$is( 'administrator: Administrator is still a role they can give', isset( get_editable_roles()['administrator'] ), true );
$is( 'administrator: can edit another administrator', current_user_can( 'edit_user', $admin2 ), true );
wp_set_current_user( $admin2 );
$is( 'administrator without a staff address is not staff', DS_Toolkit::is_leagueapps_user(), false );
update_option( 'default_role', 'subscriber' );
$is( 'administrator: default role still changes normally', get_option( 'default_role' ), 'subscriber' );

update_option( 'default_role', $before );
wp_set_current_user( 0 );
foreach ( array_merge( array( $admin, $admin2, $part, $sub ), array_filter( $made ) ) as $u ) { wp_delete_user( $u ); }
echo $fails ? "FAILED $fails of $n\n" : "ALL $n PASS\n";
