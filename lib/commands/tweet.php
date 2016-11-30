<?php

use Cranberry\CLI\Command;
use Cranberry\Core\File;
use Cranberry\Core\Utils;
use Cranberry\Core\JSON;
use Cranberry\Core\String;

/**
 * @command		tweet
 * @desc			Generate a tweet
 * @usage			tweet
 */
$command = new Command\Command( 'tweet', "Generate a tweet", function()
{
	$headlinesFile = $this->dataDirectory->child( 'headlines.json' );
	$headlinesData = JSON::decode( $headlinesFile->getContents(), true );

	$bot = new HAL4500\Bot;
	$bot->setHeadlines( $headlinesData );
	$bot->setExclusions( getExclusions() );

	do
	{
		$sentence = $bot->getSentence();

		$shouldUseSentence = true;
		$shouldUseSentence = $shouldUseSentence && substr_count( $sentence, ' ' ) >= 2;
		$shouldUseSentence = $shouldUseSentence && substr_count( $sentence, ' ' ) <= 6;
		$shouldUseSentence = $shouldUseSentence && strlen( $sentence ) <= 140;
		$shouldUseSentence = $shouldUseSentence && !$bot->stringExistsInHeadlines( $sentence );
	}
	while( !$shouldUseSentence );

	/*
	 * Capitalization
	 */
	$sentence = String::ucwords( $sentence );

	/* Hyphenates, done badly */
	$hyphenatedWords = explode( '-', $sentence );
	foreach( $hyphenatedWords as &$hyphenatedWord )
	{
		$hyphenatedWord = String::ucfirst( $hyphenatedWord );
	}
	$sentence = implode( '-', $hyphenatedWords );

	$lowerCaseWords = [
		'a',
		'an',
		'and',
		'as',
		'at',
		'in',
		'of',
		'on',
		'or',
		'the',
		'to',
		'v',
		'vs',
	];

	foreach( $lowerCaseWords as $lowerCaseWord )
	{
		$upperCaseWord = ucfirst( $lowerCaseWord );
		$sentence = str_replace( " {$upperCaseWord} ", " {$lowerCaseWord } ", $sentence );
	}

	$sentence = str_replace( 'IAd', 'iAd', $sentence );
	$sentence = str_replace( 'IBook', 'iBook', $sentence );
	$sentence = str_replace( 'ICal', 'iCal', $sentence );
	$sentence = str_replace( 'ICloud', 'iCloud', $sentence );
	$sentence = str_replace( 'ILife', 'iLife', $sentence );
	$sentence = str_replace( 'IMessage', 'iMessage', $sentence );
	$sentence = str_replace( 'IPad', 'iPad', $sentence );
	$sentence = str_replace( 'IPod', 'iPod', $sentence );
	$sentence = str_replace( 'IPhone', 'iPhone', $sentence );
	$sentence = str_replace( 'ITunes', 'iTunes', $sentence );
	$sentence = str_replace( 'IWork', 'iWork', $sentence );

	$sentence = str_replace( 'IOS', 'iOS', $sentence );
	$sentence = str_replace( 'MacOS', 'macOS', $sentence );
	$sentence = str_replace( 'TvOS', 'tvOS', $sentence );
	$sentence = str_replace( 'WatchOS', 'watchOS', $sentence );

	/* Question? */
	$sentenceWords = explode( ' ', $sentence );
	$interrogatives = ['is', 'was', 'will', 'why'];
	if( in_array( mb_strtolower( $sentenceWords[0] ), $interrogatives ) )
	{
		$sentence = "{$sentence}?";
	}

	$matchedChars = ['(', ')', '[', ']', '&quot;', '\\', '“', '”', '‘'];
	foreach( $matchedChars as $matchedChar )
	{
		$sentence = str_replace( $matchedChar, "", $sentence, $replacedMatchedChar );
	}

	/* Tidy up */
	$sentence = str_replace( '\'', '’', $sentence );
	$sentence = str_replace( '&apos;', '’', $sentence );
	$sentence = html_entity_decode( $sentence );

	// if( $this->getOptionValue( 'no-tweet' ) )
	{
		echo $sentence . PHP_EOL;
		return;
	}
});

return $command;
