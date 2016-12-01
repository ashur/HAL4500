<?php

use Cranberry\CLI\Command;
use Cranberry\Core\File;
use Cranberry\Core\JSON;

/**
 * @command		parse
 * @desc			Fetch and parse feeds
 * @usage			parse <url> [<datestamp>]
 */
$command = new Command\Command( 'parse', "Fetch and parse feeds", function( $url )
{
	$year = 2016;
	$month = 11;
	$day = 1;

	while( $year >= 2006 )
	{
		$datestamp = sprintf( '%s%02s%02s', $year, $month, $day );

		/*
		 * Cache
		 */
		$timeLastChecked = $this->cookies->get( 'feed', 'lastChecked' );
		if( !is_null( $timeLastChecked ) )
		{
			$cacheLifetime = 30;
			$timeNextCheck = $timeLastChecked + $cacheLifetime;

			if( time() < $timeNextCheck )
			{
				echo ( "! Too soon. Check again in " . ($timeNextCheck - time()) . " seconds" ) . PHP_EOL;
				sleep( 10 );
				continue;
			}
		}

		$this->cookies->set( 'feed', 'lastChecked', time() );

		/*
			 * Wayback Machine
		 */
		$waybackURL  = "http://archive.org/wayback/available?url={$url}";
		$waybackURL .= empty( $datestamp ) ? '' : "&timestamp={$datestamp}";

		$waybackJSON = file_get_contents( $waybackURL );
		$waybackData = JSON::decode( $waybackJSON, true );

		if( !isset( $waybackData['archived_snapshots']['closest'] ) )
		{
			throw new Command\CommandInvokedException( 'Weird response from the Wayback Machine', 1 );
		}

		$snapshotInfo = $waybackData['archived_snapshots']['closest'];
		if( $snapshotInfo['available'] != 1 )
		{
			echo ( '! Closest snapshot is unavailable' ) . PHP_EOL;
			return;
		}

		$snapshotsFile = $this->dataDirectory->child( 'snapshots.json' );
		if( !$snapshotsFile->exists() )
		{
			$snapshotsFile->putContents( '[]' );
		}

		$snapshotsData = JSON::decode( $snapshotsFile->getContents() );
		if( in_array( $snapshotInfo['url'], $snapshotsData ) )
		{
			echo ( '! Closest snapshot already recorded' ) . PHP_EOL;
		}

		$feedURL = $snapshotInfo['url'];

		/*
			 * Feed
		 */

		echo "> Fetching feed '{$feedURL}'...";
		$feedContents = file_get_contents( $feedURL );
		echo ' done.' . PHP_EOL;

		$snapshotsData[] = $feedURL;
		$snapshotsJSON = JSON::encode( $snapshotsData );
		$snapshotsFile->putContents( $snapshotsJSON );

		/*
			 * Parse
		 */
		$pattern = '/<title>([^<]*)/i';
		preg_match_all( $pattern, $feedContents, $matches );

		if( count( $matches[1] ) == 0 )
		{
			throw new Command\CommandInvokedException( 'Something went wrong while parsing the feed', 1 );
		}

		$titles = [];

		/* Exclusions */
		$exclusionsExactMatch = [];
		$exclusionsContains = [];

		foreach( $matches[1] as $title )
		{
			foreach( $exclusionsExactMatch as $exclusion )
			{
				if( $title == $exclusion )
				{
					continue 2;
				}
			}

			foreach( $exclusionsContains as $exclusion )
			{
				if( substr_count( $title, $exclusion ) > 0 )
				{
					continue 2;
				}
			}

			$titles[] = $title;
		}

		/* Update headlines.json */
		$headlinesFile = $this->dataDirectory->child( 'headlines.json' );
		if( !$headlinesFile->exists() )
		{
			$headlinesFile->putContents( '[]' );
		}

		$headlinesData = JSON::decode( $headlinesFile->getContents(), true );

		krsort( $titles );
		$countTitlesAdded = 0;

		foreach( $titles as $title )
		{
			if( !in_array( $title, $headlinesData ) )
			{
				array_unshift( $headlinesData, $title );
				$countTitlesAdded++;
			}
		}

		$headlinesJSON = JSON::encode( $headlinesData, JSON_PRETTY_PRINT );
		$headlinesFile->putContents( $headlinesJSON );

		echo ( "+ Added {$countTitlesAdded} new titles." ) . PHP_EOL . PHP_EOL;

		/* Adjust datestamp */
		if( $day > 1 )
		{
			$day = $day - 10;
		}
		else
		{
			/* Roll back to previous month */
			$day = 21;

			if( $month == 1 )
			{
				$month = 12;
				$year--;
			}
			else
			{
				$month--;
			}
		}
	}
});

$command->registerOption( 'use-archive' );
$command->setUsage( 'parse <url> [--use-archive <date>]' );

return $command;
