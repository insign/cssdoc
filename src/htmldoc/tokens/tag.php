<?php
namespace hexydec\html;

class tag {

	protected $root;
	protected $parent = null;
	protected $tagName = null;
	protected $attributes = Array();
	protected $singleton = false;
	protected $children = Array();
	public $close = true;

	public function __construct(htmldoc $root, string $tag = null, tag $parent = null) {
		$this->root = $root;
		$this->tagName = $tag;
		$this->parent = $parent;
	}

	/**
	 * Parses an array of tokens into an HTML documents
	 *
	 * @param array &$tokens An array of tokens generated by tokenise()
	 * @param array $config An array of configuration options
	 * @return bool Whether the parser was able to capture any objects
	 */
	public function parse(array &$tokens) {

		// cache vars
		$config = $this->root->getConfig();
		$tag = $this->tagName;
		$attributes = Array();

		// parse tokens
		$attr = false;
		while (($token = next($tokens)) !== false) {
			switch ($token['type']) {

				// remember attribute
				case 'attribute':
					if ($attr) {
						$attributes[$attr] = null;
						$attr = false;
					}
					$attr = $token['value'];
					break;

				// record attribute and value
				case 'attributevalue':
					if ($attr) {
						$value = preg_replace('/^[= \\t\\r\\n]*+["\']?|["\']?[= \\t\\r\\n]*+$/u', '', $token['value']);
						$attributes[$attr] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);

						// cache value for minifier
						if ($attr == 'class') {
							$this->root->cache('class', array_filter(explode(' ', $attributes[$attr])));
						}
						$attr = false;
					}
					break;

				case 'tagopenend':
					if (!in_array($tag, $config['elements']['singleton'])) {
						next($tokens);
						$this->children = $this->parseChildren($tokens, $config);
						break;
					} else {
						$this->singleton = $token['value'];
						break 2;
					}

				case 'tagselfclose':
					$this->singleton = $token['value'];
					break 2;

				case 'tagopenstart':
					$tag = trim($token['value'], '<');
					if ($tag == $tag) {
						$this->close = false;
					}
					prev($tokens);
					break 2;

				case 'tagclose':
					$close = trim($token['value'], '</>');
					if (strcasecmp($close, $tag) !== 0) { // if tags not the same, go back to previous level

						// if the closing tag is optional then don't close the tag
						if (in_array($tag, $config['elements']['closeoptional'])) {
							$this->close = false;
						}
						prev($tokens); // close the tag on each level below until we find itself
					}
					break 2;
			}
		}
		if ($attr) {
			$attributes[$attr] = null;
		}
		if ($attributes) {
			$this->attributes = $attributes;

			// cache attribute for minifier
			$this->root->cache('attr', array_keys($attributes));
			$this->root->cache('attrvalues', array_map(function ($val, $key) {return $key.'='.$val;}, $attributes, array_keys($attributes)));
		}
	}

	/**
	 * Parses an array of tokens into an HTML documents
	 *
	 * @param array &$tokens An array of tokens generated by tokenise()
	 * @param array $config An array of configuration options
	 * @return bool Whether the parser was able to capture any objects
	 */
	public function parseChildren(array &$tokens) : array {
		$parenttag = $this->tagName;
		$config = $this->root->getConfig('elements');
		$children = Array();

		// process custom tags
		if (in_array($parenttag, $config['custom'])) {
			$class = '\\hexydec\\html\\'.$parenttag;
			$item = new $class($this->root);
			$item->parse($tokens);
			$children[] = $item;

		// parse children
		} else {
			$tag = null;
			$token = current($tokens);
			do {
				switch ($token['type']) {
					case 'doctype':
						$item = new doctype();
						$item->parse($tokens);
						$children[] = $item;
						break;

					case 'tagopenstart':
						$tag = trim($token['value'], '<');
						if ($tag == $parenttag && in_array($tag, $config['closeoptional'])) {
							prev($tokens);
							break 2;
						} else {

							// parse the tag
							$item = new tag($this->root, $tag, $this);
							$item->parse($tokens);
							$children[] = $item;
							if (in_array($tag, $config['singleton'])) {
								$tag = null;
							}
						}
						break;

					case 'tagclose':
						prev($tokens); // close the tag on each level below until we find itself
						break 2;

					case 'textnode':
						$item = new text($this->root, $this);
						$item->parse($tokens);
						$children[] = $item;
						break;

					case 'cdata':
						$item = new cdata();
						$item->parse($tokens);
						$children[] = $item;
						break;

					case 'comment':
						$item = new comment();
						$item->parse($tokens);
						$children[] = $item;
						break;
				}
			} while (($token = next($tokens)) !== false);
		}
		return $children;
	}

	/**
	 * Minifies the internal representation of the tag
	 *
	 * @param array $minify An array of minification options controlling which operations are performed
	 * @return void
	 */
	public function minify(array $minify) : void {
		$config = $this->root->getConfig();
		$attr = $config['attributes'];
		if ($minify['lowercase']) {
			$this->tagName = mb_strtolower($this->tagName);
		}
		$folder = false;
		$dirs = false;

		// minify attributes
		foreach ($this->attributes AS $key => $value) {

			// lowercase attribute key
			if ($minify['lowercase']) {
				unset($this->attributes[$key]);
				$key = mb_strtolower($key);
				$this->attributes[$key] = $value;
			}

			// minify urls
			if ($minify['urls'] && in_array($key, $attr['urls'])) {

				// make folder variables
				if ($folder === false && isset($_SERVER['REQUEST_URI'])) {
					if (($folder = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) !== '') {
						if (mb_substr($folder, -1) != '/') {
							$folder = dirname($folder).'/';
						}
						$dirs = explode('/', trim($folder, '/'));
					}
				}

				// strip scheme from absolute URL's if the same as current scheme
				if ($minify['urls']['scheme'] && isset($_SERVER['HTTPS'])) {
					if (!isset($scheme)) {
						$scheme = 'http'.($_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off' ? 's' : '').'://';
					}
					if (mb_strpos($this->attributes[$key], $scheme) === 0) {
						$this->attributes[$key] = mb_substr($this->attributes[$key], mb_strlen($scheme)-2);
					}
				}

				// remove host for own domain
				if ($minify['urls']['host'] && isset($_SERVER['HTTP_HOST'])) {
					if (!isset($host)) {
						$host = '//'.$_SERVER['HTTP_HOST'];
						$hostlen = mb_strlen($host);
					}
					if (mb_strpos($this->attributes[$key], $host) === 0 && (mb_strlen($this->attributes[$key]) == $hostlen || mb_strpos($this->attributes[$key], '/', 2)) == $hostlen + 1) {
						$this->attributes[$key] = mb_substr($this->attributes[$key], $hostlen);
					}
				}

				// make absolute URLs relative
				if ($minify['urls']['relative'] && $folder) {

					// minify
					if (mb_strpos($this->attributes[$key], $folder) === 0 && ($folder != '/' || mb_strpos($this->attributes[$key], '//') !== 0)) {
						if ($this->attributes[$key] == $folder && $this->attributes[$key] != $_SERVER['REQUEST_URI']) {
							$this->attributes[$key] = './';
						} else {
							$this->attributes[$key] = mb_substr($this->attributes[$key], mb_strlen($folder));
						}
					}
				}

				// use parent folders if it is shorter
				if ($minify['urls']['parent'] && $dirs && mb_strpos($this->attributes[$key], '/') === 0 && mb_strpos($this->attributes[$key], '//') === false) {

					$compare = explode('/', trim(dirname($this->attributes[$key]), '/'));
					$update = false;
					$count = 0;
					foreach ($compare AS $i => $item) {
						if (isset($dirs[$i]) && $item == $dirs[$i]) {
							array_shift($compare);
							$update = true;
							$count++;
						} else {
							break;
						}
					}
					if ($update) {
						$compare = array_merge(array_fill(0, count($dirs) - $count, '..'), $compare);
						$url = implode('/', $compare).'/'.basename($this->attributes[$key]);
						if (strlen($url) <= strlen($this->attributes[$key])) { // compare as bytes
							$this->attributes[$key] = $url;
						}
					}
				}
			}

			// minify attributes
			if ($minify['attributes']) {

				// trim attribute
				$this->attributes[$key] = trim($this->attributes[$key]);

				// boolean attributes
				if ($minify['attributes']['boolean'] && in_array($key, $attr['boolean'])) {
					$this->attributes[$key] = null;

				// minify style tag
				} elseif ($key == 'style' && $minify['attributes']['style']) {
					$this->attributes[$key] = trim(str_replace(
						Array('  ', ' : ', ': ', ' :', ' ; ', ' ;', '; '),
						Array(' ', ':', ':', ':', ';', ';', ';'),
						$this->attributes[$key]
					), '; ');

				// sort classes
				} elseif ($key == 'class' && $minify['attributes']['class'] && mb_strpos($this->attributes[$key], ' ') !== false) {
					$this->attributes[$key] = implode(' ', array_intersect($minify['attributes']['class'], explode(' ', $this->attributes[$key])));

				// minify option tag
				} elseif ($key == 'value' && $minify['attributes']['option'] && $this->tagName == 'option' && isset($this->children[0]) && $this->children[0]->text() == $this->attributes[$key]) {
					unset($this->attributes[$key]);
					continue;

				// remove tag specific default attribute
				} elseif ($minify['attributes']['default'] && isset($attr['default'][$this->tagName][$key]) && ($attr['default'][$this->tagName][$key] === true || $attr['default'][$this->tagName][$key] == $this->attributes[$key])) {
					unset($this->attributes[$key]);
					continue;
				}

				// remove other attributes
				if ($this->attributes[$key] === '' && $minify['attributes']['empty'] && in_array($key, $attr['empty'])) {
					unset($this->attributes[$key]);
					continue;
				}
			}
		}

		// minify singleton closing style
		if ($minify['singleton'] && $this->singleton) {
			$this->singleton = '>';
		}

		// work out whether to omit the closing tag
		if ($minify['close'] && in_array($this->tagName, $config['elements']['closeoptional']) && !in_array($this->parent->tagName, $config['elements']['inline'])) {
			$tag = null;
			$children = $this->parent->toArray();
			$last = end($children);
			$next = false;
			foreach ($children AS $item) {

				// find self in siblings
				if ($item === $this) {
					$next = true;

				// find next tag
				} elseif ($next) {
					$type = get_class($item);

					// if type is not text or the text content is empty
					if ($type != 'hexydec\\html\\text' || !$item->content) {

						// if the next tag is optinally closable too, then we can remove the closing tag of this
						if ($type == 'hexydec\\html\\tag' && in_array($item->tagName, $config['elements']['closeoptional'])) {
							$this->close = false;
						}

						// indicate we have process this
						$next = false;
						break;
					}
				}
			}

			// if last tag, remove closing tag
			if ($next) {
				$this->close = false;
			}
		}

		// sort attributes
		if ($minify['attributes']['sort'] && $this->attributes) {
			$attr = $this->attributes;
			$this->attributes = array_replace(array_intersect_key(array_fill_keys($minify['attributes']['sort'], false), $attr), $attr);
		}

		// minify children
		if ($this->children) {
			if (in_array($this->tagName, $config['elements']['pre'])) {
				$minify['whitespace'] = false;
			}
			foreach ($this->children AS $item) {
				$item->minify($minify);
			}
		}
	}

	public function find(Array $selector) : Array {
		$found = Array();
		$match = true;
		$searchChildren = true;
		foreach ($selector AS $i => $item) {

			// only search this level
			if ($item['join'] == '>' && !$i) {
				$searchChildren = false;
			}

			// pass rest of selector to level below
			if ($item['join'] && $i) {
				$match = false;
				foreach ($this->children AS $child) {
					if (get_class($child) == 'hexydec\\html\\tag') {
						$found = array_merge($found, $child->find(array_slice($selector, $i)));
					}
				}
				break;

			// match tag
			} elseif (!empty($item['tag']) && $item['tag'] != '*') {
				if ($item['tag'] != $this->tagName) {
					$match = false;
					break;
				}

			// match id
			} elseif (!empty($item['id'])) {
				if (empty($this->attributes['id']) || $item['id'] != $this->attributes['id']) {
					$match = false;
					break;
				}

			// match class
			} elseif (!empty($item['class'])) {
				if (empty($this->attributes['class']) || !in_array($item['class'], explode(' ', $this->attributes['class']))) {
					$match = false;
					break;
				}

			// attribute selector
			} elseif (!empty($item['attribute'])) {

				// check if attribute exists
				if (empty($this->attributes[$item['attribute']])) {
					$match = false;
					break;
				} elseif (!empty($item['value'])) {

					// exact match
					if ($item['comparison'] == '=') {
						if ($this->attributes[$item['attribute']] != $item['value']) {
							$match = false;
							break;
						}

					// match start
					} elseif ($item['comparison'] == '^=') {
						if (mb_strpos($this->attributes[$item['attribute']], $item['value']) !== 0) {
							$match = false;
							break;
						}

					// match within
					} elseif ($item['comparison'] == '*=') {
						if (mb_strpos($this->attributes[$item['attribute']], $item['value']) === false) {
							$match = false;
							break;
						}

					// match end
					} elseif ($item['comparison'] == '$=') {
						if (mb_strpos($this->attributes[$item['attribute']], $item['value']) !== mb_strlen($this->attributes[$item['attribute']]) - mb_strlen($item['value'])) {
							$match = false;
							break;
						}
					}
				}

			// match pseudo selector
			} elseif (!empty($item['pseudo'])) {
				$children = $this->parent->children();

				// match first-child
				if ($item['pseudo'] == 'first-child') {
					if (!isset($children[0]) || $this !== $children[0]) {
						$match = false;
						break;
					}

				// match last child
				} elseif ($item['pseudo'] == 'last-child') {
					if (($last = end($children)) === false || $this !== $last) {
						$match = false;
						break;
					}
				}
			}
		}
		if ($match) {
			$found[] = $this;
		}
		if ($searchChildren && $this->children) {
			foreach ($this->children AS $child) {
				if (get_class($child) == 'hexydec\\html\\tag') {
					$found = array_merge($found, $child->find($selector));
				}
			}
		}
		return $found;
	}

	public function attr(string $key) : ?string {
		if (array_key_exists($key, $this->attributes)) {
			return $this->attributes[$key] === null ? true : $this->attributes[$key];
		}
		return null;
	}

	public function text() : array {
		$text = Array();
		foreach ($this->children AS $item) {

			// only get text from these objects
			if (in_array(get_class($item), Array('hexydec\\html\\tag', 'hexydec\\html\\text'))) {
				$value = $item->text();
				$text = array_merge($text, is_array($value) ? $value : Array($value));
			}
		}
		return $text;
	}

	public function html(array $options = null) : string {

		// compile attributes
		$html = '<'.$this->tagName;
		foreach ($this->attributes AS $key => $value) {
			$html .= ' '.$key;
			if ($value !== null || $options['xml']) {
				$empty = in_array($value, Array(null, ''));
				$quote = '"';
				if ($options['quotestyle'] == 'single') {
					$quote = "'";
				} elseif (!$empty && $options['quotestyle'] == 'minimal' && strcspn($value, " =\"'`<>\n\r\t/") == strlen($value)) {
					$quote = '';
				}
				if (!$empty) {
					$value = htmlspecialchars($value, ENT_HTML5 | ($options['quotestyle'] == 'single' ? ENT_QUOTES : ENT_COMPAT));
				}
				$html .= '='.$quote.$value.$quote;
			}
		}

		// close singleton tags
		if ($this->singleton) {
			$html .= empty($options['singletonclose']) ? $this->singleton : $options['singletonclose'];

		// close opening tag and compile contents
		} else {
			$html .= '>';
			foreach ($this->children AS $item) {
				$html .= $item->html($options);
			}
			if ($options['closetags'] || $this->close) {
				$html .= '</'.$this->tagName.'>';
			}
		}
		return $html;
	}

	public function toArray() {
		return $this->children;
	}

	public function children() {
		$children = Array();
		foreach ($this->children AS $item) {
			if (get_class($item) == 'hexydec\\html\\tag') {
				$children[] = $item;
			}
		}
		return $children;
	}

	public function __get($var) {
		return $this->$var;
	}
}
