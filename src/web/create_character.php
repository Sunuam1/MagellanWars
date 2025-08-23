<?php
// Process the create character page with proper variables
$IMAGE_SERVER_URL = "";  // Use relative paths
$STRING_CHARACTER_NAME = "Character Name:";
$CHAR_SET = "UTF-8";

// Read the HTML template
$html = file_get_contents('create_character.html');

// Replace all variables
$html = str_replace('$IMAGE_SERVER_URL', $IMAGE_SERVER_URL, $html);
$html = str_replace('$STRING_CHARACTER_NAME', $STRING_CHARACTER_NAME, $html);
$html = str_replace('$CHAR_SET', $CHAR_SET, $html);

// Fix image paths - use local images or placeholders
$html = str_replace('/image/as_login/create_character/create_character_title.jpg', '/images/title.png', $html);
$html = str_replace('/image/as_login/create_character/create_human.jpg', '/images/human.png', $html);
$html = str_replace('/image/as_login/create_character/create_targro.jpg', '/images/targro.png', $html);
$html = str_replace('/image/as_login/create_character/create_bukka.jpg', '/images/bukka.png', $html);
$html = str_replace('/image/as_login/create_character/create_xeloss.jpg', '/images/xeloss.png', $html);
$html = str_replace('/image/as_login/create_character/create_agerus.jpg', '/images/agerus.png', $html);
$html = str_replace('/image/as_login/create_character/create_bosalian.jpg', '/images/bosalian.png', $html);
$html = str_replace('/image/as_login/create_character/create_xeldorade.jpg', '/images/xeldorade.png', $html);
$html = str_replace('/image/as_login/create_character/create_kreen.jpg', '/images/kreen.png', $html);
$html = str_replace('/image/as_login/create_character/create_madness.jpg', '/images/madness.png', $html);
$html = str_replace('/image/as_login/create_character/create_magellan.jpg', '/images/magellan.png', $html);
$html = str_replace('/image/as_login/bu_create.gif', '/images/create_button.png', $html);

// Fix form action
$html = str_replace('ACTION=/archspace/create2.as', 'ACTION="create_character_result.html"', $html);

// Fix encyclopedia links
$html = str_replace('/encyclopedia/race/', '#race_', $html);

echo $html;
?>