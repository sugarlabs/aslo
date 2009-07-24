<?php
/**
 * This is a component to translate markdown into HTML using PHP Markdown.
 *
 * @link http://michelf.com/projects/php-markdown/
 * @link http://daringfireball.net/projects/markdown/
 * @see http://daringfireball.net/projects/markdown/syntax
 */

/**
 * Before we define our component, we need to extend the vendor code in
 * order tighten down allowed markup, support fenced code blocks,
 * and add a hook for syntax highlighting
 */
vendor('phpmarkdown'.DS.'markdown');

/**
 * AmoMarkdownParser is based on:
 *
 *   PHP Markdown & PHP Markdown Extra
 *
 * With syntax class inspiration from Python Markdown:
 *
 *   http://www.freewisdom.org/projects/python-markdown/Fenced_Code_Blocks
 *
 * PHP Markdown & Extra
 * Copyright (c) 2004-2008 Michel Fortin  
 * <http://www.michelf.com/>  
 * All rights reserved.
 * 
 * Based on Markdown  
 * Copyright (c) 2003-2006 John Gruber   
 * <http://daringfireball.net/>   
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 * 
 * * Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * 
 * * Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * 
 * * Neither the name "Markdown" nor the names of its contributors may
 *   be used to endorse or promote products derived from this software
 *   without specific prior written permission.
 */
class AmoMarkdownParser extends Markdown_Parser {

    function AmoMarkdownParser() {
        // Overide parser gamuts in order to restrict allowed markdown.
        // The parent constructor does sorting on the gamut arrays, so
        // we redefine them first.

        // gets run once on the entire document
        $this->document_gamut = array(
            "runBasicBlockGamut"   => 30,
        );

        // this can be run multiple times to support nested block elements
        $this->block_gamut = array(
            "doFencedCodeBlocks"   => 5,
            // "doHeaders"            => 10, // uncomment to allow header syntax
            // "doHorizontalRules"    => 20, // uncomment to allow HR syntax
            "doLists"              => 40,
            "doEscapedBlockQuotes" => 60,
        );

        // this gets run on block content
        $this->span_gamut = array(
            "doItalicsAndBold"    =>  50,
            "doHardBreaks"        =>  60,
        );

        parent::Markdown_Parser();

        // even though we encode all markup before the parser is run,
        // turning this off will make some of the default regexs simpler
        $this->no_markup = true;
    }

    /**
     *  One (singleton) instance is all we ever need
     */
    function getInstance() {
        static $instance;
        if (!isset($instance)) {
            $c = __CLASS__;
            $instance = new $c;
        }
        return $instance;
    }

   /**
    * Add the fenced code block syntax to regular Markdown:
    *
    * ~~~ {.optional-syntax-class}
    * Code block
    * ~~~
    *
    * @param string $text 
    * @return string
    */
    function doFencedCodeBlocks($text) {
        $text = preg_replace_callback('{
                (?:\n|\A)
                # 1: Opening marker
                (
                    ~{3,} # Marker: three tilde or more.
                )

                # 2: Optional syntax declaration following marker.
                (
                    [ ]*                # padding
                    [{]?                # optional braces
                    [.]([a-zA-Z0-9_-]+) # 3: syntax class identifier
                    [}]?                # optional braces
                )?

                [ ]* \n # Whitespace and newline following marker/syntax.
                
                # 4: Content
                (
                    (?>
                        (?!\1 [ ]* \n)	# Not a closing marker.
                        .*\n+
                    )+
                )
                
                # Closing marker.
                \1 [ ]* \n
            }xm',
            array(&$this, '_doFencedCodeBlocks_callback'), $text);

        return $text;
    }

    /**
     * Callback to format fenced code blocks with optional syntax class
     *
     * @param array $matches
     * @return string
     */
    function _doFencedCodeBlocks_callback($matches) {
        $brush = $matches[3];
        $codeblock = $matches[4];

        $syntaxClass = empty($brush) ? '' : " class=\"brush:{$brush}\"";

        return "\n\n".$this->hashBlock("<pre{$syntaxClass}>{$codeblock}</pre>")."\n\n";
    }

    /**
     * Generate blockquotes from lines beginning with encoded '>'s
     *
     * Note that markdown allows you to be lazy and put the '>' only
     * on the first line of a multi-line block of input. In this case,
     * the block ends at the first blank line encountered.
     *
     * > this is
     * > one blockquote
     *
     * > this is
     * a second blockquote
     *
     * this is not in a blockquote
     *
     * @param string $text
     * @return string
     */
	function doEscapedBlockQuotes($text) {
		$text = preg_replace_callback('/
              (                         # Wrap whole match in $1
                (?>
                  ^[ ]*&(gt|[#]62);[ ]? # "&gt;" or "&#62;" at the start of a line
                    .+\n                # rest of the first line
                  (.+\n)*               # subsequent consecutive lines
                  \n*                   # blanks
                )+
              )
            /xm',
			array(&$this, '_doEscapedBlockQuotes_callback'), $text);

		return $text;
	}

    /**
     * Callback for blockquote parsing
     *
     * @param array $matches
     * @return string
     */
	function _doEscapedBlockQuotes_callback($matches) {
		$bq = $matches[1];
		// trim one level of quoting - trim whitespace-only lines
		$bq = preg_replace('/^[ ]*&(gt|[#]62);[ ]?|^[ ]+$/m', '', $bq);
		$bq = $this->runBlockGamut($bq); // recurse

		return "\n". $this->hashBlock("<blockquote>\n$bq\n</blockquote>")."\n\n";
	}
}
 
class MarkdownComponent extends Object {
    var $controller;

    /**
     * This parser needs to know how to handle sanitized markdown
     */
    var $parser;

    /**
     * Save a reference to the controller on startup
     * @param object &$controller the controller using this component
     */
    function startup(&$controller) {
        $this->controller =& $controller;
        $this->parser = AmoMarkdownParser::getInstance();
    }

    /**
     * Translate markdown to html
     *
     * @param mixed $text string or array to translate
     * @return mixed string or array or translated input
     */
    function html($text) {
        if (is_array($text)) {
            foreach ($text as $k => &$v) {
                $text[$k] = $this->html($v);
            }
        }
        else if (is_string($text)) {
            // be paranoid, and sanitize markdown text before processing
            $this->controller->_sanitizeArray($text);
            $text = $this->parser->transform($text);
        }
        return $text;
    }

    /**
     * Translate to html only array values with a specific key
     *
     * @param array $arr array of data to potentially translate
     * @param string $key only string values with this key will be translated
     * @return mixed string or array or translated input
     */
    function htmlForKey($arr, $key) {
        if (is_array($arr)) {
            foreach ($arr as $k => &$v) {
                if (is_array($v)) {
                    $arr[$k] = $this->htmlForKey($v, $key);
                }
                elseif (($k === $key) && is_string($v)) {
                    $arr[$k] = $this->html($v);
                }
            }
        }
        return $arr;
    }
}
