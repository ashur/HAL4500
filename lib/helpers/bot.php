<?php

use Cranberry\Bot\History;

/*
 * Bot
 */
$bot = new HAL4500\Bot();

/*
 * Set History
 */
$historyFile = $dataDirectory->child( 'history.json' );
$history = new History\History( $historyFile );
$bot->setHistoryObject( $history );

$app->registerCommandObject( 'bot', $bot );
