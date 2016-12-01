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
	/*
	 * Setup
	 */
	$headlinesFile = $this->dataDirectory->child( 'headlines.json' );
	$headlinesData = JSON::decode( $headlinesFile->getContents(), true );

	shuffle( $headlinesData );
	$sampleSize = floor( count( $headlinesData ) * 0.8 );
	$headlines = array_slice( $headlinesData, 0, $sampleSize );

	$this->bot->setHeadlines( $headlines );
	$this->bot->setExclusions( getExclusions() );

	$sentence = $this->bot->getSentence();

	/*
	 * Capitalization
	 */
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
		'by',
		'for',
		'from',
		'in',
		'its',
		'is',
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
	$sentence = str_replace( 'IPhoto', 'iPhoto', $sentence );
	$sentence = str_replace( 'ITunes', 'iTunes', $sentence );
	$sentence = str_replace( 'IWatch', 'iWatch', $sentence );
	$sentence = str_replace( 'IWork', 'iWork', $sentence );

	$sentence = str_replace( 'IOS', 'iOS', $sentence );
	$sentence = str_replace( 'MacOS', 'macOS', $sentence );
	$sentence = str_replace( 'TvOS', 'tvOS', $sentence );
	$sentence = str_replace( 'WatchOS', 'watchOS', $sentence );

	/* Question? */
	$sentenceWords = explode( ' ', $this->bot->getNormalizedString( $sentence ) );
	$interrogatives = [
		'are',
		'does',
		'how does',
		'how many',
		'how will',
		'is',
		'was',
		'will',
		'what if',
		'which',
		'who',
		'why do',
		'why is',
		'why isn’t',
		'why are'
	];
	if( in_array( mb_strtolower( $sentenceWords[0] ), $interrogatives ) )
	{
		$sentence = "{$sentence}?";
	}

	/*
	 * Clean up unwanted characters
	 */
	$matchedChars = ['(', ')', '[', ']', '&quot;', '\\', '“', '”', '‘'];
	foreach( $matchedChars as $matchedChar )
	{
		$sentence = str_replace( $matchedChar, "", $sentence, $replacedMatchedChar );
	}

	/* Tidy up */
	$sentence = str_replace( '\'', '’', $sentence );
	$sentence = str_replace( '&apos;', '’', $sentence );
	$sentence = str_replace( '...', '…', $sentence );
	$sentence = html_entity_decode( $sentence );

	/* Trailing weirdos */
	$unwantedTrailers = [ ':', ',', ' ', '.' ];
	foreach( $unwantedTrailers as $unwantedTrailer )
	{
		if( substr( $sentence, -1, 1 ) == $unwantedTrailer )
		{
			$sentence = substr( $sentence, 0, String::strlen( $sentence ) - 1 );
		}
	}

	/* Re-capitalize after a ':' */
	if( ($colonPos = strpos( $sentence, ': ' )) !== false )
	{
		$charAfterColon = substr( $sentence, $colonPos + 2, 1 );
		$charAfterColon = String::strtoupper( $charAfterColon );
		$sentence = substr_replace( $sentence, $charAfterColon, $colonPos + 2, 1 );
	}

	/*
	 * Tweet Prefix
	 */
	$prefixes = [ 'Seminar:', 'Guest Lecture:', 'Lecture:' ];

	/* Course Number */
	$prefixLength = 2;
	$coursePrefixes = [];

	/* Always skip the first word */
	for( $w = 1; $w < count( $sentenceWords ); $w++ )
	{
		$word = $sentenceWords[$w];

		/* Don't include short words */
		if( String::strlen( $word ) >= $prefixLength + 2 )
		{
			if( !is_numeric( substr( $word, 0, $prefixLength ) ) )
			{
				$coursePrefixes[] = $word;
			}
		}
	}

	if( count( $coursePrefixes ) > 0 )
	{
		$courseSectionPrefix = rand( 1, 8 ) . '0';
		$courseSectionSuffix = sprintf( '%02s', rand( 0, 99 ) );
		$courseSection = $courseSectionPrefix . $courseSectionSuffix;

		$coursePrefix = Utils::getRandomElement( array_slice( $coursePrefixes, 0, ceil( count( $coursePrefixes ) / 2 ) ) );
		$coursePrefix = String::strtoupper( $coursePrefix );
		$coursePrefix = substr( $coursePrefix, 0, $prefixLength );

		$course = sprintf( '%s-%s', $coursePrefix, $courseSection );
		$prefixes = array_merge( $prefixes, [$course, $course, $course, $course] );

		/* Sort by word length */
		usort( $coursePrefixes, function( $a, $b )
		{
	    	return strlen( $b ) - strlen( $a );
		});
	}

	$sentence = sprintf( '%s “%s”', Utils::getRandomElement( $prefixes ), $sentence );

	// if( $this->getOptionValue( 'no-tweet' ) )
	{
		echo $sentence . PHP_EOL;
		// return;
	}

	$this->bot->writeHistory();
});

return $command;
