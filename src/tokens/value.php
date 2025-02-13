<?php
declare(strict_types = 1);
namespace hexydec\css;
use \hexydec\tokens\tokenise;

class value {

	/**
	 * @var rule The parent rule object
	 */
	protected $root;

	/**
	 * @var value Properties
	 */
	protected $properties = [];

	/**
	 * Constructs the property object
	 *
	 * @param cssdoc $root The parent htmldoc object
	 */
	public function __construct($root) {
		$this->root = $root;
	}

	/**
	 * Parses CSS tokens
	 *
	 * @param tokenise &$tokens A tokenise object
	 * @param array $config An array of configuration options
	 * @return bool Whether anything was parsed
	 */
	public function parse(tokenise $tokens) : bool {
		$comment = null;
		while (($token = $tokens->next()) !== null) {
			switch ($token['type']) {
				case 'string':
				case 'join':
					$value = [];
					do {
						if (\in_array($token['type'], ['string', 'join'])) {
							$value[] = $token['value'];
						} else {
							$tokens->prev();
							break;
						}
					} while (($token = $tokens->next()) !== false);
					$this->properties[] = implode('', $value);
					break;
				case 'colon':
				case 'quotes':
				case 'comma':
					$this->properties[] = $token['value'];
					break;
				case 'bracketopen':
					$item = new value($this->root);
					if ($item->parse($tokens)) {
						$this->properties[] = $item;
					}
					break;
				case 'comment':
					$comment = $token['value'];
					break;
				case 'semicolon':
				case 'curlyopen':
				case 'curlyclose':
				case 'important':
					$tokens->prev();
				case 'bracketclose':
					break 2;
			}
		}
		return !empty($this->properties);
	}

	/**
	 * Minifies the internal representation of the comment
	 *
	 * @param array $minify An array of minification options controlling which operations are performed
	 * @return void
	 */
	public function minify(array $minify, string $key = null) : void {
		$last = null;
		foreach ($this->properties AS &$item) {

			// value in brackets
			if (is_object($item)) {
				$item->minify($minify, $last);
			} else {
				if ($minify['removezerounits'] && \preg_match('/^0(?:\.0*)?([a-z%]++)$/i', $item, $match)) {
					$item = '0';
					if ($match[1] == 'ms') {
						$match[1] = 's';
					}
					if ($match[1] == 's') {
						$item .= 's';
					}
				}
				if ($minify['removeleadingzero'] && \preg_match('/^0++(\.0*+[1-9][0-9%a-z]*+)$/', $item, $match)) {
					$item = $match[1];
				}
				if (!in_array($key, ['content', 'format']) && $minify['removequotes'] && preg_match('/^("|\')([^ \'"()]++)\\1$/i', $item, $match)) {
					$item = $match[2];
				} elseif ($minify['convertquotes'] && \mb_strpos($item, "'") === 0) {
					$item = '"'.\addcslashes(\stripslashes(\trim($item, "'")), "'").'"';
				}
				if ($minify['shortenhex'] && \preg_match('/^#(([a-f0-6])\\2)(([a-f0-6])\\4)(([a-f0-6])\\6)/i', $item, $match)) {
					$item = '#'.$match[2].$match[4].$match[6];
				}
				if ($minify['lowervalues'] && \mb_strpos($item, '"') === false) {
					$item = \mb_strtolower($item);
				}
				$last = $item;
			}
		}
		unset($item);
	}

	/**
	 * Compile the property to a string
	 *
	 * @param array $options An array of compilation options
	 * @return void
	 */
	public function compile(array $options) : string {
		$b = $options['output'] != 'minify';
		$css = $options['prefix'];
		$join = '';
		$last = null;
		foreach ($this->properties AS $item) {
			if (\is_object($item)) {
				if ($last == 'and') {
					$css .= $join;
				}
				$css .= '('.$item->compile($options).')';
				$join = ' ';
			} elseif (\in_array($item, [':', '-', '+', ','])) {
				$css .= $item;
				$join = '';
			} else {
				$css .= $join.$item;
				$join = ' ';
			}
			$last = $item;
		}
		return $css;
	}
}
