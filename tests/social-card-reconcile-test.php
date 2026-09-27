<?php
// Run: wp eval-file tests/social-card-reconcile-test.php (uses two existing image attachments, writes nothing).
if ( ! defined( 'WP_CLI' ) ) { exit; }
$ok = 0; $bad = 0; $is = function ( $n, $a, $b ) use ( &$ok, &$bad ) { if ( $a === $b ) { $ok++; } else { $bad++; echo "FAIL $n: " . var_export( $a, 1 ) . "\n"; } };
$att = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 2, 'fields' => 'ids' ) );
list( $a, $b ) = $att; $ua = wp_get_attachment_url( $a ); $ub = wp_get_attachment_url( $b );
$is( 'same image, same host: kept', DS_Social_Card::reconcile( $a, $ua ), array( $a, $ua ) );
$is( 'URL names another image: URL wins', DS_Social_Card::reconcile( $a, $ub ), array( $b, $ub ) );
$moved = preg_replace( '#^https?://[^/]+#', 'https://old-temp-domain.wpenginepowered.com', $ua );
$is( 'old host, same file: id kept, URL made current', DS_Social_Card::reconcile( $a, $moved ), array( $a, $ua ) );
$is( 'old host, id missing: found by path', DS_Social_Card::reconcile( 0, $moved ), array( $a, $ua ) );
$is( 'foreign URL of a different file: stale id dropped', DS_Social_Card::reconcile( $a, 'https://cdn.example.com/other.png' ), array( 0, 'https://cdn.example.com/other.png' ) );
$is( 'empty URL: id gives the URL', DS_Social_Card::reconcile( $a, '' ), array( $a, $ua ) );
echo $bad ? "FAILED $bad\n" : "ALL $ok PASS\n";
