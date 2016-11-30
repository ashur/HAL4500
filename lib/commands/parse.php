<?php

use Cranberry\CLI\Command;
use Cranberry\Core\File;
use Cranberry\Core\JSON;

/**
 * @command		parse
 * @desc			Parse the DF feed
 * @usage			parse <url> [<datestamp>]
 */
$command = new Command\Command( 'parse', "Parse the DF feed", function( $url, $datestamp=null )
{
	/*
	 * Cache
	 */
	$timeLastChecked = $this->cookies->get( 'feed', 'lastChecked' );
	if( !is_null( $timeLastChecked ) )
	{
		$cacheLifetime = 60 * 60 * 12;
		$cacheLifetime = 30;
		$timeNextCheck = $timeLastChecked + $cacheLifetime;
		$dateNextCheck = date( 'H:i', $timeNextCheck );

		if( time() < $timeNextCheck )
		{
			$this->output->line( "Too soon. Check again at {$dateNextCheck}" );
			return;
		}
	}

	$this->cookies->set( 'feed', 'lastChecked', time() );

	$feedURL = $url;
	$snapshotsFile = $this->dataDirectory->child( 'snapshots.json' );

	if( $this->getOptionValue( 'use-archive' ) == true )
	{
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
			$this->output->line( 'Closest snapshot is unavailable' );
			return;
		}

		if( !$snapshotsFile->exists() )
		{
			$snapshotsFile->putContents( '[]' );
		}

		$snapshotsData = JSON::decode( $snapshotsFile->getContents() );
		if( in_array( $snapshotInfo['url'], $snapshotsData ) )
		{
			$this->output->line( 'Closest snapshot already recorded' );
		}

		$feedURL = $snapshotInfo['url'];
	}

	/*
 	 * Feed
	 */

	echo "Fetching feed '{$feedURL}'...";
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

		/* Cleanup */
		$title = str_replace( '★ ', '', $title );

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

	$headlinesJSON = JSON::encode( $headlinesData );
	$headlinesFile->putContents( $headlinesJSON );

	$this->output->line( "Added {$countTitlesAdded} new titles." );
});

$command->registerOption( 'use-archive' );
$command->setUsage( 'parse <url> [--use-archive <date>]' );

return $command;
