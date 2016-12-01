<?php

function getExclusions()
{
	$exclusions = [
		'abus',
		'bitch',
		'black',
		'dead',
		'died',
		'dies',
		'hitler',
		'hobo',
		'homo',
		'inmate',
		'jew',
		'kill',
		'nazi',
		'nigg',
		'prison',
		'rape',
		'rip',
		'shoot',
		'slave',

		// Diseases
		'aids',
		'alcoholism',
		'cancer',
		'hiv',
		'insane',

		// Too easy to make light of tragic events
		'bernardino',
		'central park five',
		'massacre',
		'newtown',

		// Too personal, or breaks illusion
		'kottke',
		'minna',
		'ollie',

		'/',
		'sponsor',
	];

	return $exclusions;
}
