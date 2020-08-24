<?php
//========================================================================
// Author:  Pascal KISSIAN
// Resume:  http://pascal.kissian.net
//
// Copyright (c) 2015-2018 Pascal KISSIAN
//
// Published under the MIT License
//          Consider it as a proof of concept!
//          No warranty of any kind.
//          Use and abuse at your own risks.
//========================================================================

const PHP_PARSER_DIRECTORY  = 'PHP-Parser';
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter;
	
function encode_file($file,$save_file = ''){
	define('STDERR',fopen('php://output','w+'));
	
	global $argv;
	global $yakpro_po_dirname;
	global $source_file;
	global $conf;
	global $t_scrambler;
	global $t_pre_defined_class_constants;
	global $t_pre_defined_class_properties;
	global $t_pre_defined_classes;
	global $t_pre_defined_class_methods;
	
	$argv = array(
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
		$file
	);
		
	require_once __DIR__.'/include/check_version.php';
	require_once __DIR__.'/include/get_default_defined_objects.php';
	require_once __DIR__.'/include/classes/config.php';
	require_once __DIR__.'/include/classes/scrambler.php';
	require_once __DIR__.'/include/functions.php';
	require_once __DIR__.'/version.php';
	require_once __DIR__.'/include/retrieve_config_and_arguments.php';
	require_once __DIR__.'/include/classes/parser_extensions/my_autoloader.php';
	require_once __DIR__.'/include/classes/parser_extensions/my_pretty_printer.php';
	require_once __DIR__.'/include/classes/parser_extensions/my_node_visitor.php';

	//$conf->parser_mode = 'PREFER_PHP7';

	switch($conf->parser_mode)
	{
	    case 'PREFER_PHP7': $parser_mode = ParserFactory::PREFER_PHP7;  break;
	    case 'PREFER_PHP5': $parser_mode = ParserFactory::PREFER_PHP5;  break;
	    case 'ONLY_PHP7':   $parser_mode = ParserFactory::ONLY_PHP7;    break;
	    case 'ONLY_PHP5':   $parser_mode = ParserFactory::ONLY_PHP5;    break;
	    default:            $parser_mode = ParserFactory::PREFER_PHP5;  break;
	}

	global $parser;
	$parser = (new ParserFactory)->create($parser_mode);

	global $traverser;
	$traverser = new NodeTraverser;

	global $prettyPrinter;
	if ($conf->obfuscate_string_literal) $prettyPrinter = new myPrettyprinter;
	else $prettyPrinter = new PrettyPrinter\Standard;

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
	    return;
	}

	$traverser->addVisitor(new MyNodeVisitor);
	$target_file = $save_file;
	$obfuscated_str =  obfuscate($source_file);
	if ($obfuscated_str === null) { echo 'error: obfuscated_str is empty';return; }
	if ($target_file === '') { echo $obfuscated_str.PHP_EOL; return; }
	file_put_contents($target_file,$obfuscated_str.PHP_EOL);
}

//encode_file('sqlsafe.class.php','sqlsafe_enc.class.php');
?>