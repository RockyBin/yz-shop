<?php
function encode_file($file,$save_file = ''){
	define('STDERR',fopen('php://output','w+'));
	global $argv;
	global $source_file;
	global $conf;
	global $t_scrambler;
	global $t_pre_defined_class_constants;
	global $t_pre_defined_class_properties;
	global $t_pre_defined_classes;
	global $t_pre_defined_class_methods;
	
	$argv = array(
		'aaa',
		'--silent',
		//'--no-obfuscate-variable-name',
		'--no-obfuscate-constant-name',
		'--no-obfuscate-function-name',
		'--no-obfuscate-class-name',
		'--no-obfuscate-property-name',
		'--no-obfuscate-method-name',
		'--no-obfuscate-class_constant-name',
		'--no-obfuscate-interface-name',
		'--no-obfuscate-namespace-name',
		'--no-obfuscate-trait-name',
		'--no-obfuscate-label-name',
		//'--scramble-mode',
		//'numeric',
		//'--scramble-length',
		//'32',
		$file);

	require_once __DIR__.'/include/check_version.php';
	require_once __DIR__.'/include/get_default_defined_objects.php';
	require_once __DIR__.'/PHP-Parser/lib/bootstrap.php';
	require_once __DIR__.'/include/classes/config.php';
	
	require_once __DIR__.'/include/classes/scrambler.php';
	require_once __DIR__.'/include/classes/parser_extensions/my_pretty_printer.php';
	require_once __DIR__.'/include/classes/parser_extensions/my_node_visitor.php';
	require_once __DIR__.'/include/functions.php';
	include		 __DIR__.'/include/retrieve_config_and_arguments.php';
	require_once __DIR__.'/version.php';
	
	global $parser;
	$parser             = new PhpParser\Parser(new PhpParser\Lexer\Emulative);      // $parser = new PhpParser\Parser(new PhpParser\Lexer);
	global $traverser;
	$traverser          = new PhpParser\NodeTraverser;
	
	global $prettyPrinter;
	if ($conf->obfuscate_string_literal)    $prettyPrinter      = new myPrettyprinter;
	else                                    $prettyPrinter      = new PhpParser\PrettyPrinter\Standard;

	
	$t_scrambler = array();
	foreach(array('variable','function','method','property','class','class_constant','constant','label') as $scramble_what)
	{
		$t_scrambler[$scramble_what] = new Scrambler($scramble_what, $conf, ($process_mode=='directory') ? $target_directory : null);
	}
	if ($whatis!=='')
	{
		if ($whatis{0} == '$') $whatis = substr($whatis,1);
		foreach(array('variable','function','method','property','class','class_constant','constant','label') as $scramble_what)
		{
			if ( ( $s = $t_scrambler[$scramble_what]-> unscramble($whatis)) !== '')
			{
				switch($scramble_what)
				{
					case 'variable':
					case 'property':
						$prefix = '$';
						break;
					default:
						$prefix = '';
				}
				echo "$scramble_what: {$prefix}{$s}".PHP_EOL;
			}
		}
		exit;
	}

	$traverser->addVisitor(new MyNodeVisitor);
	$target_file = $save_file;
	$obfuscated_str =  obfuscate($source_file);
	if ($obfuscated_str === null) { die('error'); }
	if ($target_file === '') { echo $obfuscated_str.PHP_EOL; exit; }
	file_put_contents($target_file,$obfuscated_str.PHP_EOL);
}

//encode_file('H:\YouZhanGit\IWorkSite\SiteUI\Public\sqlsafe.class.php','H:\YouZhanGit\IWorkSite\SiteUI\Public\sqlsafe2.class.php');
//encode_file('H:\YouZhanGit\IWorkSite\SiteUI\Public\sqlsafe2.class.php','H:\YouZhanGit\IWorkSite\SiteUI\Public\sqlsafe3.class.php');
?>