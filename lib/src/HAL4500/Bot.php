<?php

/**
 * This file is part of HAL4500
 */
namespace HAL4500;

use Cranberry\Core\String;
use Cranberry\Core\Utils;

class Bot
{
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

			$pattern = "/(\s|^){$currentWord}\s([^\s]*)/m";
			$headlinesString = str_replace( '’', '\'', $this->headlinesString );
			preg_match_all( $pattern, $headlinesString, $matches );

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
	 * @return	string
	 */
	public function getSentence()
	{
		/* Lines */
		$baddies = ['.', '?', '(', ')', '*', '-', '+', '[', ']', '\\', '/', '?'];

		$words = [];
		$nextWord = '';
		$attempts = 0;

		do
		{
			$nextWord = $this->getNextWord( $nextWord );

			foreach( $baddies as $baddie )
			{
				$nextWord = str_replace( $baddie, "\\{$baddie}", $nextWord );
			}

			$attempts++;
			if( $attempts >= 10 )
			{
				return '';
			}

			if( $nextWord != false )
			{
				$words[] = $nextWord;
			}
		}
		while( $nextWord != false );

		foreach( $words as &$word )
		{
			if( substr( $word, -1 ) == '\'' )
			{
				$word = substr( $word, 0, strlen( $word ) - 1 );
			}
		}

		$sentence = implode( ' ', $words );
		$sentence = String::ucfirst( $sentence );

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
		$this->headlines = $headlines;
		$this->headlinesString = implode( PHP_EOL . PHP_EOL, $headlines );
	}

	/**
	 * @param	string	$string
	 * @return	boolean
	 */
	public function stringExistsInHeadlines( $string )
	{
		$normalizedString = String::strtolower( $string );
		$normalizedString = str_replace( '‘', '', $normalizedString );
		$normalizedString = str_replace( '’', '', $normalizedString );
		$normalizedString = str_replace( '\'', '', $normalizedString );

		foreach( $this->headlines as $headline )
		{
			$normalizedHeadline = $headline;
			$normalizedHeadline = String::strtolower( $normalizedHeadline );
			$normalizedHeadline = str_replace( '‘', '', $normalizedHeadline );
			$normalizedHeadline = str_replace( '’', '', $normalizedHeadline );
			$normalizedHeadline = str_replace( '\'', '', $normalizedHeadline );

			// echo "> {$normalizedHeadline}" . PHP_EOL;

			if( substr_count( $normalizedHeadline, $normalizedString ) > 0 )
			{
				// echo ">>> Found '{$normalizedString}' in '{$normalizedHeadline}'" . PHP_EOL;
				return true;
			}
		}

		// echo "# {$normalizedString}" . PHP_EOL;
		return false;
	}
}
