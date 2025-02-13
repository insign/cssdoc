<?php
declare(strict_types = 1);
namespace hexydec\css;
use \hexydec\tokens\tokenise;

class directive {

	/**
	 * @var rule The parent rule object
	 */
	protected $root;

	/**
	 * @var string The name of the directive
	 */
	protected $directive;

	/**
	 * @var string The value of the directive
	 */
	protected $content = [];

	/**
	 * @var array An array of properties
	 */
	protected $properties = [];

	/**
	 * Constructs the comment object
	 *
	 * @param cssdoc $root The parent htmldoc object
	 */
	public function __construct(document $root) {
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
		if (($token = $tokens->current()) !== null) {
			$directive = true;
			$properties = false;
			do {
				switch ($token['type']) {
					case 'directive':
						$this->directive = $token['value'];
						break;
					case 'string':
					case 'colon':
					case 'bracketopen':
						if ($properties) {
							$item = new property($this);
							if ($item->parse($tokens)) {
								$this->properties[] = $item;
							}
							break;
						}
					case 'quotes':
						$tokens->prev();
						$item = new value($this);
						if ($item->parse($tokens)) {
							$this->content[] = $item;
						}
						break;
					case 'curlyopen':
						// next($tokens);
						if (in_array($this->directive, ['@media', '@keyframes', '@supports'])) {
							$item = new document($this);
							if ($item->parse($tokens)) {
								$this->properties[] = $item;
							}
						} else {
							$properties = true;
						}
						break;
					case 'semicolon':
					case 'curlyclose':
						break 2;
				}
			} while (($token = $tokens->next()) !== null);
		}
		return !empty($this->directive);
	}

	/**
	 * Minifies the internal representation of the comment
	 *
	 * @param array $minify An array of minification options controlling which operations are performed
	 * @return void
	 */
	public function minify(array $minify) : void {

		// minify directive properties
		foreach ($this->content AS $item) {
			$item->minify($minify);
		}

		// minify properties
		foreach ($this->properties AS $item) {
			$item->minify($minify);
		}

		if ($this->properties && $minify['removesemicolon']) {
			$this->properties[count($this->properties)-1]->semicolon = false;
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
		$css = $this->directive;
		$join = ' ';
		foreach ($this->content AS $item) {
			$css .= $join.$item->compile($options);
			$join = $b ? ', ' : ',';
		}
		if ($this->properties) {
			$css .= $b ? ' {' : '{';

			// compile properties
			$tab = $b ? "\n\t" : '';
			foreach ($this->properties AS $item) {
				$css .= $tab.$item->compile($options);
			}
			$css .= $b ? "\n".$options['prefix'].'}' : '}';
		} else {
			$css .= ';';
		}
		return $css;
	}
}
