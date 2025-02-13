<?php
require(__DIR__.'/vendor/autoload.php');

$base = empty($_POST['base']) ? '' : $_POST['base'];
$input = '';
$output = '';
$minify = Array();
$timing = Array(
	'start' => microtime(true)
);
$mem = Array(
	'start' => memory_get_usage()
);

// create object and retrieve config
$doc = new hexydec\css\cssdoc();
$options = $doc->config;

// process form submmission
if (!empty($_POST['action'])) {
	$timing['fetch'] = microtime(true);
	$mem['fetch'] = memory_get_usage();

	// handle a URL
	if (!empty($_POST['url'])) {

		// parse the URL
		if (($url = parse_url($_POST['url'])) === false) {
			trigger_error('Could not parse URL: The URL is not valid', E_USER_WARNING);

		// check the host name
		} elseif (!isset($url['host'])) {
			trigger_error('Could not parse URL: No host was supplied', E_USER_WARNING);

		// open the document
		} elseif (($input = $doc->open($_POST['url'], null, $error)) === false) {
			trigger_error('Could not load CSS: '.$error, E_USER_WARNING);

		// save base URL
		} else {
			$base = $_POST['url'];
		}

	// handle directly entered source code
	} elseif (empty($_POST['source'])) {
		trigger_error('No URL or CSS source was posted', E_USER_WARNING);

	// load the source code
	} elseif (!$doc->load($_POST['source'], null, $error)) {
		trigger_error('Could not parse CSS: '.$error, E_USER_WARNING);

	// record the HTML
	} else {
		$input = $_POST['source'];
	}

	// if there is some input
	if ($input) {
		$timing['parse'] = microtime(true);
		$mem['parse'] = memory_get_usage();

		// retrieve the user posted options
		$isset = isset($_POST['minify']) && is_array($_POST['minify']);
		foreach ($options AS $key => $item) {
			$minify[$key] = $isset && in_array($key, $_POST['minify']) ? (is_array($item) ? [] : (is_bool($options[$key]) ? true : $options[$key])) : false;
			if (is_array($item)) {
				foreach ($item AS $sub => $value) {
					if ($minify[$key] !== false && isset($_POST['minify'][$key]) && is_array($_POST['minify'][$key]) && in_array($sub, $_POST['minify'][$key])) {
						$minify[$key][$sub] = true;
					} elseif ($minify[$key]) {
						$minify[$key][$sub] = false;
					}
				}
			}
		}

		// minify the input
		if ($minify) {
			$doc->minify($minify);
		}

		// record timings
		$timing['minify'] = microtime(true);
		$mem['minify'] = memory_get_usage();
		$output = $doc->save();
		$timing['output'] = microtime(true);
		$mem['output'] = memory_get_usage();
	}
} else {
	$minify = $options;
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Hexydec CSS Minifier</title>
		<style>
			html, body {
				margin: 0;
				font-family: Segoe UI;
			}
			.minify__form {
				height: 100vh;
				display: flex;
			}
			.minify__form-wrap {
				display: flex;
				flex-direction: column;
				flex: 1 1 auto;
			}
			.minify__form-heading {
				margin: 10px 10px 0 10px;
				flex: 0 0 auto;
			}
			.minify__form-input {
				flex: 1 1 auto;
				display: flex;
				flex-direction: column;
				margin: 10px 10px 0 10px;
			}
			.minify__form-url {
				display: flex;
				margin: 10px 10px 0 10px;
			}
			.minify__form-url-box {
				flex: 1 1 auto;
			}
			.minify__form-input-box {
				display: block;
				box-sizing: border-box;
				width: 100%;
				flex: 1 1 auto;
			}
			.minify__table {
				margin: 10px;
				font-size: 0.9em;
			}
			.minify__table th, .minify__table td {
				padding: 5px;
				text-align: center;
				border-bottom: 1px solid #CCC;
			}
			.minify__table td:first-child {
				text-align: left;
				font-weight: bold;
			}
			.minify__preview {
				flex: 1 1 40%;
			}
			.minify__options {
				flex: 0 0 150px;
				padding: 10px;
				background: #003ea4;
				color: #FFF;
			}
			.minify__options-list {
				list-style: none;
				margin: 0;
				padding: 0;
			}
			.minify__options-list .minify__options-list {
				padding-left: 20px;
			}
		</style>
	</head>
	<body>
		<form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" accept-charset="<?= mb_internal_encoding(); ?>" class="minify__form">
			<div class="minify__form-wrap">
				<h1 class="minify__form-heading">CSS Minifier</h1>
				<div class="minify__form-input">
					<label for="source">Paste CSS:</label>
					<textarea name="source" id="source" class="minify__form-input-box"><?= htmlspecialchars($input); ?></textarea>
				</div>
				<div class="minify__form-url">
					<label for="url">or External URL:</label>
					<input type="url" name="url" id="url" class="minify__form-url-box" />
					<button name="action" value="url">Go</button>
				</div>
				<?php if ($output) { ?>
					<div class="minify__form-input">
						<label for="output">Output CSS:</label>
						<textarea id="output" class="minify__form-input-box"><?= htmlspecialchars($output); ?></textarea>
					</div>
					<table class="minify__table">
						<thead>
							<tr>
								<th></th>
								<th>Input (bytes)</th>
								<th>Output (bytes)</th>
								<th>Diff (bytes)</th>
								<th>% of Original</th>
								<th></th>
								<th>Load</th>
								<th>Parse</th>
								<th>Minify</th>
								<th>Output</th>
								<th>Total (sec) / Peak (kb)</th>
							</tr>
						</thead>
						<tbody>
							<?php
								$ilen = strlen($input);
								$olen = strlen($output);
								$gilen = strlen(gzencode($input));
								$golen = strlen(gzencode($output));
							?>
							<tr>
								<td>Uncompressed</td>
								<td><?= htmlspecialchars(number_format($ilen)); ?></td>
								<td><?= htmlspecialchars(number_format($olen)); ?></td>
								<td><?= htmlspecialchars(number_format($ilen - $olen)); ?></td>
								<td><?= htmlspecialchars(number_format((100 / $ilen) * $olen)); ?>%</td>
								<td style="font-weight:bold;">Time (sec)</td>
								<td><?= htmlspecialchars(number_format($timing['fetch'] - $timing['start'], 4)); ?>s</td>
								<td><?= htmlspecialchars(number_format($timing['parse'] - $timing['fetch'], 4)); ?>s</td>
								<td><?= htmlspecialchars(number_format($timing['minify'] - $timing['parse'], 4)); ?>s</td>
								<td><?= htmlspecialchars(number_format($timing['output'] - $timing['minify'], 4)); ?>s</td>
								<td><?= htmlspecialchars(number_format($timing['output'] - $timing['fetch'], 4)); ?>s</td>
							</tr>
							<tr>
								<td>Compressed</td>
								<td><?= htmlspecialchars(number_format($gilen)); ?></td>
								<td><?= htmlspecialchars(number_format($golen)); ?></td>
								<td><?= htmlspecialchars(number_format($gilen - $golen)); ?></td>
								<td><?= htmlspecialchars(number_format((100 / $gilen) * $golen)); ?>%</td>
								<td style="font-weight:bold;">Memory (kb)</td>
								<td><?= htmlspecialchars(number_format($mem['fetch'] / 1024, 0)); ?>kb</td>
								<td><?= htmlspecialchars(number_format($mem['parse'] / 1024, 0)); ?>kb</td>
								<td><?= htmlspecialchars(number_format($mem['minify'] / 1024, 0)); ?>kb</td>
								<td><?= htmlspecialchars(number_format($mem['output'] / 1024, 0)); ?>kb</td>
								<td><?= htmlspecialchars(number_format(memory_get_peak_usage() / 1024, 0)); ?>kb</td>
							</tr>
						</tbody>
					</table>
				<?php } ?>
			</div>
			<div class="minify__options">
				<h3>Options</h3>
				<ul class="minify__options-list">
					<?php foreach ($options AS $key => $item) { ?>
						<li>
							<label>
								<input type="checkbox" name="minify[]" value="<?= $key; ?>"<?= !isset($minify[$key]) || $minify[$key] === false ? '' : ' checked="checked"'; ?> /><?= htmlspecialchars(ucfirst($key)); ?>
							</label>
							<?php if (is_array($item)) { ?>
								<ul class="minify__options-list">
									<?php foreach ($item AS $sub => $value) { ?>
										<li>
											<label>
												<input type="checkbox" name="minify[<?= $key; ?>][]" value="<?= $sub; ?>"<?= !isset($minify[$key][$sub]) || $minify[$key][$sub] === false ? '' : ' checked="checked"'; ?> /><?= htmlspecialchars(ucfirst($sub)); ?>
											</label>
										</li>
									<?php } ?>
								</ul>
							<?php } ?>
						</li>
					<?php } ?>
				</ul>
			</div>
		</form>
	</body>
</html>
