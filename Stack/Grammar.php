<?php

namespace Hodos\Stack;

final class Grammar
{
	private string $word;
	
	private static array $vowels = ['a', 'e', 'i', 'o', 'u'];
	
	private static array $plurals = [
		'eaf' => 'eaves',
		'ch' => 'ches',
		'fe' => 'ves',
		'is' => 'es',
		'on' => 'a',
		'sh' => 'shes',
		'us' => 'i',
		'o' => 'oes',
		's' => 'ses',
		'x' => 'xes',
		'y' => ['ys', 'ies'],
		'z' => 'zes',
	];
	
	public function __construct(string $word)
	{
		$this->word = $word;
		// $this->getPlural();
	}
	
	/**
	 * Summary of getPlural
	 *
	 * @return array|string|null
	 */
	public function getPlural()
	{
		$word = $this->word;
		$match = $this->matchPluralReplacement($word);
		return !empty($match->replacement) ? preg_replace("/$match->matched_key$/", $match->replacement, $word) : $word . 's';
	}
	
	/**
	 * Summary of matchPluralReplacement
	 *
	 * @param string $word
	 * @return object{matched_key:string,replacement:string}
	 */
	private function matchPluralReplacement(string $word)
	{
		$matched_keys = [];
		$plural_keys = array_keys($this::$plurals);
		
		foreach ($plural_keys as $key => $plural_key) {
			preg_match("/$plural_key$/", $word, $matches);
			if (!empty($matches[0]))
				$matched_keys[] = $plural_key;
		}
		$matched_key = array_reduce($matched_keys, fn ($carry, $item) => strlen($item) > strlen($carry) ? $item : $carry);
		$replacement = !empty($matched_key) ? $this::$plurals[$matched_key] : NULL;
		if (!empty($replacement) && is_array($replacement))
			$replacement = $this->getConsonantPlural($word, $matched_key, $replacement);
		return (object) compact('matched_key', 'replacement');
	}
	
	/**
	 * Summary of getConsonantPlural
	 *
	 * @param string $word
	 * @param string $matched_key
	 * @param array $replacement
	 * @return mixed
	 */
	private function getConsonantPlural(string $word, string $matched_key, array $replacement)
	{
		$chars = str_split($word);
		$char = array_reverse($chars)[strlen($matched_key)];
		return (in_array($char, $this::$vowels)) ? $replacement[0] : $replacement[1];
	}
}
