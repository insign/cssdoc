<?php
declare(strict_types = 1);
namespace hexydec\css;

class rule {

	/**
	 * @var mediaquery The parent htmldoc object
	 */
	protected $root;

	/**
	 * @var array An array of selectors
	 */
	protected $selectors = [];

	/**
	 * @var array An array of properties
	 */
	protected $properties = [];

	/**
	 * Constructs the comment object
	 *
	 * @param cssdoc $root The parent htmldoc object
	 */
	public function __construct(mediaquery $root) {
		$this->root = $root;
	}

	/**
	 * Parses an array of tokens into an HTML documents
	 *
	 * @param array &$tokens An array of tokens generated by tokenise()
	 * @param array $config An array of configuration options
	 * @return void
	 */
	public function parse(array &$tokens) : void {

		// parse tokens
		$selector = true;
		while (($token = next($tokens)) !== false) {
			switch ($token['type']) {
				case 'string':
					if ($selector) {
						prev($tokens);
						$item = new selector($this);
						$item->parse($tokens);
						$this->selectors[] = $item;
					} else {
						prev($tokens);
						$item = new property($this);
						$item->parse($tokens);
						$this->properties[] = $item;
					}
					break;
				case 'curlyopen':
					$selector = false;
					break;
				case 'curlyclose':
					$selector = true;
					break 2;
			}
		}
	}

	// protected function parseSelectors(array &$tokens) {
	// 	$selector = [];
	// 	$join = false;
	// 	$token = current($tokens);
	// 	do {
	// 		switch ($token['type']) {
	// 			case 'whitespace':
	// 				if (!$join) {
	// 					$join = ' ';
	// 				}
	// 				break;
	// 			case 'string':
	// 				$selector[] = [
	// 					'selector' => $token['value'],
	// 					'join' => $join
	// 				];
	// 				$join = false;
	// 				break;
	// 			case 'colon':
	// 				$parts = ':';
	// 				$brackets = false;
	// 				while (($token = next($tokens)) !== false) {
	//
	// 					// capture brackets
	// 					if ($brackets || $token['type'] == 'bracketopen') {
	// 						$brackets = true;
	// 						if ($token['type'] != 'whitespace') {
	// 							$parts .= $token['value'];
	// 							if ($token['type'] == 'bracketclose') {
	// 								break;
	// 							}
	// 						}
	//
	// 					// capture selector
	// 					} elseif (!in_array($token['type'], ['whitespace', 'comma', 'curlyopen'])) {
	// 						$parts .= $token['value'];
	//
	// 					// stop here
	// 					} else {
	// 						prev($tokens);
	// 						break;
	// 					}
	// 				}
	//
	// 				// save selector
	// 				$selector[] = [
	// 					'selector' => $parts,
	// 					'join' => $join
	// 				];
	// 				$join = false;
	// 				break;
	// 			case 'squareopen':
	// 				$parts = '';
	// 				while (($token = next($tokens)) !== false) {
	// 					if ($token['type'] != 'whitespace') {
	// 						if ($token['type'] != 'squareclose') {
	// 							$parts .= $token['value'];
	// 						} else {
	// 							prev($tokens);
	// 							break;
	// 						}
	// 					}
	// 				}
	// 				$selector[] = [
	// 					'selector' => '['.$parts.']',
	// 					'join' => $join
	// 				];
	// 				$join = false;
	// 				break;
	// 			case 'curlyopen':
	// 			case 'comma':
	// 				break 2;
	// 			case 'join':
	// 				$join = $token['value'];
	// 				break;
	// 		}
	// 	} while (($token = next($tokens)) !== false);
	// 	return $selector;
	// }
	//
	// protected function parseProperties(array &$tokens) {
	// 	while (($token = next($tokens)) !== false) {
	// 		if ($token['type'] == 'string') {
	// 			$prop = $token['value'];
	// 			while (($token = next($tokens)) !== false) {
	// 				if ($token['type'] == 'colon') {
	// 					$important = false;
	// 					$properties[] = [
	// 						'property' => $prop,
	// 						'value' => self::parsePropertyValue($tokens, $important, $propcomment),
	// 						'important' => $important,
	// 						'semicolon' => ';',
	// 						'comment' => $propcomment
	// 					];
	// 					break;
	// 				}
	// 			}
	//
	// 		// end rule
	// 		} elseif ($token['type'] == 'curlyclose') {
	// 			if ($selectors && $properties) {
	// 				$rules[] = [
	// 					'selectors' => $selectors,
	// 					'properties' => $properties,
	// 					'comment' => $comment
	// 				];
	// 			}
	// 			$selectors = [];
	// 			$properties = [];
	// 			$select = true;
	// 			$comment = false;
	// 		}
	// 	}
	// }
	//
	// protected function parsePropertyValue(array &$tokens, bool &$important = false, string &$comment = null) {
	// 	$properties = [];
	// 	$values = [];
	// 	$important = false;
	// 	$comment = null;
	// 	while (($token = next($tokens)) !== false) {
	// 		if ($token['type'] == 'comma') {
	// 			$properties[] = $values;
	// 			$values = [];
	// 		} elseif ($token['value'] == '!important') {
	// 			$important = true;
	// 		} elseif ($token['type'] == 'bracketopen') {
	// 			$values[] = self::parsePropertyValue($tokens);
	// 		} elseif (in_array($token['type'], ['semicolon', 'bracketclose'])) {
	// 			while (($token = next($tokens)) !== false) {
	// 				if ($token['type'] == 'comment') {
	// 					$comment = $token['value'];
	// 				} elseif ($token['type'] != 'whitespace') {
	// 					prev($tokens);
	// 					break;
	// 				}
	// 			}
	// 			break;
	// 		} elseif ($token['type'] == 'curlyclose') {
	// 			prev($tokens);
	// 			break;
	// 		} elseif ($token['type'] != 'whitespace') {
	// 			$values[] = $token['value'];
	// 		}
	// 	}
	// 	if ($values) {
	// 		$properties[] = $values;
	// 	}
	// 	return $properties;
	// }

	/**
	 * Minifies the internal representation of the comment
	 *
	 * @param array $minify An array of minification options controlling which operations are performed
	 * @return void
	 */
	public function minify(array $minify) : void {
	}

	/**
	 * Compile the property to a string
	 *
	 * @param array $options An array of compilation options
	 * @return void
	 */
	public function compile(array $options) : string {
		$b = $options['output'] != 'minify';
		$css = '';

		// compile selectors
		$join = '';
		foreach ($this->selectors AS $item) {
			$css .= $join.$item->compile($options);
			$join = $b ? ', ' : ',';
		}
		$css .= $b ? ' {' : '{';

		// compile properties
		$tab = $b ? "\n\t" : '';
		foreach ($this->properties AS $item) {
			$css .= $tab.$item->compile($options);
		}
		$css .= $b ? "\n}" : '}';
		return $css;
	}
}
