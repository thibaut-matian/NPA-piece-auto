<?php

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$wordpress_path = rtrim($argv[1] ?? '', '/\\');
if (!$wordpress_path || !is_file($wordpress_path . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/test-contact-unread.php WORDPRESS_PATH\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require $wordpress_path . '/wp-load.php';

function assert_contact_unread($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

$ids = [];
$failed = false;
try {
    assert_contact_unread(in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true), 'Local WordPress only.');
    $admins = get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 1]);
    assert_contact_unread(!empty($admins), 'Administrator available.');
    wp_set_current_user($admins[0]);
    $baseline = npa_contact_unread_count();
    assert_contact_unread($baseline !== null, 'Existing unread count available.');

    foreach (['First', 'Second'] as $name) {
        $id = wp_insert_post(['post_type' => 'npa_contact', 'post_status' => 'private', 'post_title' => "Temporary unread test $name"], true);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }
        $ids[] = $id;
    }
    assert_contact_unread(npa_contact_unread_count() === $baseline + 2, 'New messages are counted as unread.');

    global $menu;
    $menu = [['Messages contact', 'edit_posts', 'edit.php?post_type=npa_contact']];
    npa_contact_update_menu_badge();
    assert_contact_unread(strpos($menu[0][0], 'count-' . ($baseline + 2)) !== false, 'Menu badge shows the unread count.');

    $_GET = ['post' => $ids[0], 'action' => 'edit'];
    wp_set_current_user(0);
    npa_contact_mark_opened_read();
    assert_contact_unread(npa_contact_unread_count() === $baseline + 2, 'Unauthorized opening cannot mark a message read.');

    wp_set_current_user($admins[0]);
    npa_contact_mark_opened_read();
    assert_contact_unread(npa_contact_unread_count() === $baseline + 1, 'Authorized opening marks the message read.');
    assert_contact_unread(strpos($menu[0][0], 'count-' . ($baseline + 1)) !== false, 'Badge updates immediately after opening.');
    npa_contact_mark_opened_read();
    assert_contact_unread(npa_contact_unread_count() === $baseline + 1, 'Opening again does not change the count.');

    wp_trash_post($ids[1]);
    assert_contact_unread(npa_contact_unread_count() === $baseline, 'Messages in the trash are not counted.');
    wp_untrash_post($ids[1]);
    assert_contact_unread(npa_contact_unread_count() === $baseline + 1, 'Restoring an unread message counts it again.');

    update_post_meta($ids[1], '_npa_contact_read', '1');
    assert_contact_unread(npa_contact_unread_count() === $baseline, 'Read state is shared, not per user.');
    echo "PASS: tests completed without sending email.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $failed = true;
} finally {
    foreach ($ids as $id) {
        if (!wp_delete_post($id, true)) {
            fwrite(STDERR, "FAIL: cannot delete temporary message $id.\n");
            $failed = true;
        }
    }
}
exit($failed ? 1 : 0);
