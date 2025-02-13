<?php
declare(strict_types = 1);
namespace hexydec\css;
use \hexydec\tokens\tokenise;

class document {

	/**
	 * @var cssdoc The parent htmldoc object
	 */
	protected $root;

	/**
	 * @var array An array of media query parameters
	 */
	protected $media = [];

	/**
	 * @var array An array of child token objects
	 */
	protected $rules = [];

	/**
	 * Constructs the comment object
	 *
	 * @param cssdoc $root The parent htmldoc object
	 */
	public function __construct($root, array $media = null) {
		$this->root = $root;
		$this->media = $media;
	}

	/**
	 * Parses CSS tokens
	 *
	 * @param tokenise &$tokens A tokenise object
	 * @param array $config An array of configuration options
	 * @return bool Whether anything was parsed
	 */
	public function parse(tokenise $tokens) : bool {

		// parse tokens
		while (($token = $tokens->next()) !== null) {
			switch ($token['type']) {
				case 'directive':
					$item = new directive($this);
					$item->parse($tokens);
					$this->rules[] = $item;
					break;
				case 'curlyclose':
					$tokens->prev();
					break 2;
				case 'comment':
				case 'whitespace':
					break;
				default:
					$item = new rule($this);
					if ($item->parse($tokens)) {
						$this->rules[] = $item;
					}
					break;
			}
		}
		return !!$this->rules;
	}

	/**
	 * Minifies the internal representation of the comment
	 *
	 * @param array $minify An array of minification options controlling which operations are performed
	 * @return void
	 */
	public function minify(array $minify) : void {
		foreach ($this->rules AS $item) {
			$item->minify($minify);
		}
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
		foreach ($this->rules AS $item) {
			$css .= $join.$item->compile($options);
			$join = $b ? "\n\n" : '';
		}
		if ($this->media) {
			$css .= '}';
		}
		return $css;
	}
}
