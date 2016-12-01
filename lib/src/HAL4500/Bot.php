<?php

/**
 * This file is part of HAL4500
 */
namespace HAL4500;

use Cranberry\Core\String;
use Cranberry\Core\Utils;

class Bot
{
	use \Cranberry\Bot\History\Bot;

	/**
	 * @var	array
	 */
	protected $exclusions = [];

	/**
	 * @var	array
	 */
	protected $headlines = [];

	/**
	 * @param	string	$currentWord
	 * @return	string
	 */
	public function getNextWord( $currentWord )
	{
		if( strlen( $currentWord ) == 0 )
		{
			$nextWordCandidates = [];
			foreach( $this->headlines as $headline )
			{
				$headlineWords = explode( ' ', trim( $headline ) );
				$nextWordCandidates[] = $headlineWords[0];
			}
		}
		else
		{
			$currentWord = str_replace( '\\', '', $currentWord );

			$pattern = "/(\s|\-|^){$currentWord}\s([^\s]*)/m";
			$headlinesString = str_replace( '’', '\'', $this->headlinesString );
			@preg_match_all( $pattern, $headlinesString, $matches );

			if( !isset( $matches[2] ) || count( $matches[2] ) == 0 )
			{
				return false;
			}

			$nextWordCandidates = $matches[2];
		}

		$attempts = 0;
		do
		{
			$shouldUseWord = true;
			$nextWord = trim( Utils::getRandomElement( $nextWordCandidates ) );

			foreach( $this->exclusions as $exclusion )
			{
				if( substr_count( String::strtolower( $nextWord ), String::strtolower( $exclusion ) ) > 0 )
				{
					$shouldUseWord = false;
					break;
				}
			}

			if( strlen( $nextWord ) == 0 )
			{
				$shouldUseWord = false;
			}

			if( $attempts >= 10 )
			{
				$nextWord = false;
				break;
			}

			$attempts++;
		}
		while( !$shouldUseWord );

		return $nextWord;
	}

	/**
 	 * @param	string	$string
	 * @return	string
	 */
	public function getNormalizedString( $string )
	{
		$normalizedString = String::strtolower( $string );

		$removees = [ '\'', '"', '‘', '’', '\\', '.', '*', '$' ];
		$normalizedString = str_replace( $removees, '', $normalizedString, $count );

		return $normalizedString;
	}

	/**
	 * @return	string
	 */
	public function getSentence()
	{
		/* Lines */
		$escapees = ['.', '-', '+', '[', ']', '\\', '/'];
		$removees = ['"', '(', ')', '?', '|', '*', ',.'];	// Note: ',.' is an old typo on Kottke's feed

		do
		{
			$words = [];
			$nextWord = '';

			$wordAttempts = 0;
			$shouldUseSentence = true;

			do
			{
				$nextWord = $this->getNextWord( $nextWord );

				if( $nextWord == false )
				{
					break;
				}

				foreach( $escapees as $escapee )
				{
					$nextWord = str_replace( $escapee, "\\{$escapee}", $nextWord );
				}
				foreach( $removees as $removee )
				{
					$nextWord = str_replace( $removee, '', $nextWord );
				}

				$words[] = $nextWord;
			}
			while( $wordAttempts <= 10 );

			$sentence = implode( ' ', $words );
			$sentence = String::ucwords( $sentence );

			$shouldUseSentence = $shouldUseSentence && !$this->history->domainEntryExists( 'titles', $sentence );
			$shouldUseSentence = $shouldUseSentence && !$this->stringExistsInHeadlines( $sentence );

			/* Length */
			$shouldUseSentence = $shouldUseSentence && substr_count( $sentence, ' ' ) >= 2;
			$shouldUseSentence = $shouldUseSentence && substr_count( $sentence, ' ' ) <= 5;
			$shouldUseSentence = $shouldUseSentence && strlen( $sentence ) <= 140;
		}
		while( !$shouldUseSentence );

		$this->history->addDomainEntry( 'titles', $sentence );

		return $sentence;
	}

	/**
	 * @param	array	$exclusions
	 */
	public function setExclusions( array $exclusions )
	{
		$this->exclusions = $exclusions;
	}

	/**
	 * @param	array	$headlines
	 */
	public function setHeadlines( array $headlines )
	{
		foreach( $headlines as $headline )
		{
			$headlineNormalized = html_entity_decode( $headline, ENT_COMPAT | ENT_XML1 );
			$headlineNormalized = str_replace( '&apos;', '’', $headlineNormalized );

			/* Truncated feed titles are a bummer */
			if( substr( $headlineNormalized, -3 ) == '...' )
			{
				$headlineNormalized = substr( $headlineNormalized, 0, strrpos( $headlineNormalized, ' ' ) );
			}

			$this->headlines[] = $headlineNormalized;
		}

		$this->headlinesString = implode( PHP_EOL . PHP_EOL, $this->headlines );
	}

	/**
	 * @param	string	$string
	 * @return	boolean
	 */
	public function stringExistsInHeadlines( $string )
	{
		$normalizedString = $this->getNormalizedString( $string );
		$words = explode( ' ', $normalizedString );
		$firstWord = array_shift( $words );

		/* Strip off leading articles, which tend to throw off repeat detection */
		$articles = ['a', 'an', 'the'];
		if( in_array( $firstWord, $articles ) )
		{
			$normalizedString = implode( ' ', $words );
		}

		foreach( $this->headlines as $headline )
		{
			$normalizedHeadline = $this->getNormalizedString( $headline );

			if( substr_count( $normalizedHeadline, $normalizedString ) > 0 )
			{
				return true;
			}
		}

		return false;
	}
}
