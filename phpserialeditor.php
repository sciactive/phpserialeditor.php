<?php
/**
 * A serialized PHP value editor.
 *
 * This file helps edit values from a Nymph database, which are often
 * stored as serialized PHP. It's all self contained in this one file, including
 * the graphics.
 *
 * Copyright 2008-2018 Hunter Perrin
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Hunter can be contacted at hperrin@gmail.com
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0
 * @author Hunter Perrin <hperrin@gmail.com>
 * @copyright SciActive.com
 * @link http://sciactive.com/
 * @version 1.1
 */

// Set this to true if you don't trust your users. (And you most likely shouldn't!)
$secure_mode = true;

if (!empty($_REQUEST['type'])) {
  try {
    switch ($_REQUEST['type']) {
      case 'serialized':
      default:
        $value = unserialize($_REQUEST['value']);
        header('Content-Type: text/plain');
        switch ($_REQUEST['language']) {
          case 'yaml':
          default:
            $output = Spyc::YAMLDump($value, false, false, true);
            break;
          case 'json':
            $output = json_indent(json_encode($value));
            break;
          case 'php':
            $output = str_replace('stdClass::__set_state(', '(object) (', var_export($value, true));
            break;
        }
        break;
      case 'exported':
        switch ($_REQUEST['language']) {
          case 'yaml':
          default:
            $value = Spyc::YAMLLoadString($_REQUEST['value']);
            break;
          case 'json':
            $value = json_decode($_REQUEST['value'], true);
            break;
          case 'php':
            if ($secure_mode)
              $value = 'I told you, PHP mode is disabled!';
            else
              $value = eval('return '.$_REQUEST['value'].';');
            break;
        }
        header('Content-Type: text/plain');
        $output = serialize($value);
        break;
      case 'favicon':
        header('Content-Type: image/x-icon');
        $output = get_fav_icon();
        break;
      case 'header':
        header('Content-Type: image/png');
        $output = get_header();
        break;
    }
  } catch (Exception $e) {
    $ouput = 'Error: '.$e->getMessage();
  }
  echo $output;
  exit;
}

?>
<!DOCTYPE html>
<html>
  <head>
    <title>Serialized PHP Editor</title>
    <meta charset="UTF-8" />
    <link href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?type=favicon" type="image/vnd.microsoft.icon" rel="icon">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
    <script src="https://netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
    <?php if ($secure_mode) { ?>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pnotify/1.3.1/jquery.pnotify.default.min.css">
      <script src="https://cdnjs.cloudflare.com/ajax/libs/pnotify/1.3.1/jquery.pnotify.min.js"></script>
    <?php } ?>
    <script type="text/javascript">
      var wikEdDiffConfig; if (wikEdDiffConfig === undefined) { wikEdDiffConfig = {}; }
      wikEdDiffConfig.fullDiff = true;

      $(function(){
        var updating = false, original = '';
        var diff = $('#diff'), output = $('#output');
        var serialized = $('#serialized').on('change keyup', function(){
          if (updating)
            return;
          original = serialized.val();
          $.post('', {type: 'serialized', 'value': original, 'language': $('input[name=language]:checked').val()}, function(data){
            updating = true;
            editor.val(data);
            updating = false;
            editor.change();
          });
        });
        $('input[name=language]').on('change', function(){
          serialized.trigger('change');
        });
        var editor = $('#editor').on('change keyup', function(){
          if (updating)
            return;
          $.post('', {type: 'exported', 'value': editor.val(), 'language': $('input[name=language]:checked').val()}, function(data){
            updating = true;
            output.val(data);
            var wikEdDiff = new WikEdDiff();
            var diffHtml = wikEdDiff.diff(pretty_php_serialized(original), pretty_php_serialized(data));
            diff.html(diffHtml);
            updating = false;
          });
        });
        serialized.trigger('change');
      });

      function pretty_php_serialized(serialized) {
        serialized = serialized.replace(/<br\/?>/g, '').replace(/&([a-z0-9#]+);/gi, '**ent($1)ent**');
        while (serialized.match(/\{[^\n]/) !== null) {
          serialized = serialized.replace(/\{([^\n])/g, '{\n$1');
        }
        while (serialized.match(/\}[^\n]/) !== null) {
          serialized = serialized.replace(/\}([^\n])/g, '}\n$1');
        }
        while (serialized.match(/[^\n]\}/) !== null) {
          serialized = serialized.replace(/([^\n])\}/g, '$1\n}');
        }
        while (serialized.match(/;[^\n]/) !== null) {
          serialized = serialized.replace(/;([^\n])/g, ';\n$1');
        }
        while (serialized.match(/\{\n\}/) !== null) {
          serialized = serialized.replace(/\{\n\}/g, '{}');
        }
        var cur_indent = 1;
        var cur_entry_index = false;
        var lines = serialized.split('\n');
        serialized = '';
        for (var i = 0; i < lines.length; i++) {
          var is_a_closer = lines[i].charAt(0) == '}';
          if (is_a_closer) {
            cur_indent--;
            serialized += Array(cur_indent).join('  ') + lines[i] + '\n';
          } else {
            if (cur_entry_index) {
              serialized += Array(cur_indent).join('  ') + lines[i];
            } else {
              serialized += lines[i] + '\n';
            }
            cur_entry_index = !cur_entry_index;
          }
          if (lines[i].charAt(lines[i].length-1) == '{') {
            cur_indent++;
          }
        }
        serialized = serialized.replace(/\*\*ent\(([a-z0-9#]+)\)ent\*\*/gi, '&$1;');
        return serialized;
      }

      function do_example() {
        var example = 'a:1:{s:13:"533616e6f0a3d";a:4:{s:4:"name";s:4:"Home";s:'+
        '7:"buttons";a:34:{i:0;a:2:{s:9:"component";s:12:"com_calendar";s:6:"b'+
        'utton";s:8:"calendar";}i:1;s:9:"separator";i:2;a:2:{s:9:"component";s'+
        ':13:"com_configure";s:6:"button";s:6:"config";}i:3;s:9:"separator";i:'+
        '4;a:2:{s:9:"component";s:11:"com_content";s:6:"button";s:10:"categori'+
        'es";}i:5;a:2:{s:9:"component";s:11:"com_content";s:6:"button";s:5:"pa'+
        'ges";}i:6;a:2:{s:9:"component";s:11:"com_content";s:6:"button";s:8:"p'+
        'age_new";}i:7;s:9:"separator";i:8;a:2:{s:9:"component";s:12:"com_cust'+
        'omer";s:6:"button";s:9:"customers";}i:9;a:2:{s:9:"component";s:12:"co'+
        'm_customer";s:6:"button";s:12:"customer_new";}i:10;s:9:"separator";i:'+
        '11;a:2:{s:9:"component";s:12:"com_elfinder";s:6:"button";s:12:"file_m'+
        'anager";}i:12;s:9:"separator";i:13;a:2:{s:9:"component";s:14:"com_men'+
        'ueditor";s:6:"button";s:7:"entries";}i:14;a:2:{s:9:"component";s:14:"'+
        'com_menueditor";s:6:"button";s:9:"entry_new";}i:15;s:9:"separator";i:'+
        '16;a:2:{s:9:"component";s:11:"com_modules";s:6:"button";s:7:"modules"'+
        ';}i:17;a:2:{s:9:"component";s:11:"com_modules";s:6:"button";s:10:"mod'+
        'ule_new";}i:18;s:9:"separator";i:19;a:2:{s:9:"component";s:9:"com_pla'+
        'za";s:6:"button";s:11:"getsoftware";}i:20;a:2:{s:9:"component";s:9:"c'+
        'om_plaza";s:6:"button";s:9:"installed";}i:21;s:9:"separator";i:22;a:2'+
        ':{s:9:"component";s:11:"com_reports";s:6:"button";s:8:"rankings";}i:2'+
        '3;s:9:"separator";i:24;a:2:{s:9:"component";s:9:"com_sales";s:6:"butt'+
        'on";s:5:"sales";}i:25;a:2:{s:9:"component";s:9:"com_sales";s:6:"butto'+
        'n";s:8:"sale_new";}i:26;a:2:{s:9:"component";s:9:"com_sales";s:6:"but'+
        'ton";s:11:"countsheets";}i:27;a:2:{s:9:"component";s:9:"com_sales";s:'+
        '6:"button";s:14:"countsheet_new";}i:28;a:2:{s:9:"component";s:9:"com_'+
        'sales";s:6:"button";s:7:"receive";}i:29;a:2:{s:9:"component";s:9:"com'+
        '_sales";s:6:"button";s:7:"pending";}i:30;a:2:{s:9:"component";s:9:"co'+
        'm_sales";s:6:"button";s:9:"shipments";}i:31;s:9:"separator";i:32;a:2:'+
        '{s:9:"component";s:8:"com_user";s:6:"button";s:10:"my_account";}i:33;'+
        'a:2:{s:9:"component";s:8:"com_user";s:6:"button";s:6:"logout";}}s:12:'+
        '"buttons_size";s:5:"large";s:7:"columns";a:3:{s:13:"533616e6f0a46";a:'+
        '2:{s:4:"size";d:0.25;s:7:"widgets";a:2:{s:13:"533616e6f0975";a:3:{s:9'+
        ':"component";s:9:"com_about";s:6:"widget";s:8:"newsfeed";s:7:"options'+
        '";a:0:{}}s:13:"533616e6f0a08";a:3:{s:9:"component";s:11:"com_content"'+
        ';s:6:"widget";s:9:"quickpage";s:7:"options";a:0:{}}}}s:13:"533616e6f0'+
        'a4e";a:2:{s:4:"size";d:0.33333333333333;s:7:"widgets";a:2:{s:13:"5336'+
        '16e6f09b6";a:3:{s:9:"component";s:12:"com_calendar";s:6:"widget";s:6:'+
        '"agenda";s:7:"options";a:0:{}}s:13:"533616e6f0a33";a:3:{s:9:"componen'+
        't";s:7:"com_hrm";s:6:"widget";s:7:"clockin";s:7:"options";a:0:{}}}}s:'+
        '13:"533616e6f0a53";a:2:{s:4:"size";d:0.41666666666667;s:7:"widgets";a'+
        ':1:{s:13:"533616e6f09e0";a:3:{s:9:"component";s:13:"com_configure";s:'+
        '6:"widget";s:7:"welcome";s:7:"options";a:0:{}}}}}}}';
        $('#serialized').val(example).trigger('change');
      }
      <?php if ($secure_mode) { ?>
        var stack = {'dir1': 'up', 'dir2': 'left', 'firstpos1': 25, 'firstpos2': 25};
        $.pnotify.defaults.stack = stack;
        $.pnotify.defaults.addclass = 'stack-bottomright';
        $(function(){
          var n = $.pnotify({
            title: 'PHP Mode Disabled',
            text: 'PHP language mode has been disabled for security reasons.',
            type: 'info',
            history: false,
            styling: 'bootstrap3',
            nonblock: true,
            nonblock_opacity: .2
          }).click(function(){n.pnotify_remove()});
        });
      <?php } ?>
    </script>
  </head>
  <body>
    <div class="container-fluid">
      <header class="page-header clearfix">
        <div style="float: right; position: relative;">
          <a href="http://nymph.io" target="_blank">
            <img style="height: 65px;" src="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?type=header" alt="Nymph Logo" style="border: none;" />
          </a>
        </div>
        <h1>Serialized PHP Editor</h1>
      </header>
      <div class="form-group">
        <label>Choose a language to use for editing</label>
        <div>
          <div class="btn-group" data-toggle="buttons">
            <label class="btn btn-default active"><input type="radio" name="language" value="yaml" checked="checked" /> YAML</label>
            <label class="btn btn-default"><input type="radio" name="language" value="json" /> JSON</label>
            <label class="btn btn-default<?php if ($secure_mode) { ?> disabled<?php } ?>"><input type="radio" name="language" value="php" <?php if ($secure_mode) { ?>disabled="disabled"<?php } ?> /> PHP</label>
          </div>
        </div>
      </div>
      <div class="row" style="height: 200px;">
        <div class="col-sm-6" style="display: flex; flex-direction: column; height: 100%;">
          <div>1. Paste in serialized PHP here: <small>(<a href="javascript:void(0);" onclick="do_example();">example</a>)</small></div>
          <textarea rows="6" cols="30" id="serialized" style="width: 100%; flex-grow: 1;"></textarea>
        </div>
        <div class="col-sm-6" style="display: flex; flex-direction: column; height: 100%;">
          <div>3. The new serialized PHP will appear here:</div>
          <textarea rows="6" cols="30" id="output" style="width: 100%; flex-grow: 1;"></textarea>
        </div>
      </div>
      <div class="row" style="height: 600px; max-height: 60vh;">
        <div class="col-sm-6" style="display: flex; flex-direction: column; height: 100%;">
          <div>2. Then edit the value here:</div>
          <textarea rows="20" cols="30" id="editor" style="width: 100%; flex-grow: 1;"></textarea>
        </div>
        <div class="col-sm-6" style="display: flex; flex-direction: column; height: 100%;">
          <div>4. A colored diff will show here:</div>
          <div id="diff_container" style="flex-grow: 1; max-height: 100%; overflow: auto;">
            <div id="diff"></div>
          </div>
        </div>
      </div>
      <div>
        <a href="https://github.com/sciactive/phpserialeditor.php">This tool is open source.</a>
      </div>
    </div>
  </body>
<script type="text/javascript">
// <syntaxhighlight lang="JavaScript">

// ==UserScript==
// @name        wikEd diff
// @version     1.2.4
// @date        October 23, 2014
// @description improved word-based diff library with block move detection
// @homepage    https://en.wikipedia.org/wiki/User:Cacycle/diff
// @source      https://en.wikipedia.org/wiki/User:Cacycle/diff.js
// @author      Cacycle (https://en.wikipedia.org/wiki/User:Cacycle)
// @license     released into the public domain
// ==/UserScript==

/**
 * wikEd diff: inline-style difference engine with block move support
 *
 * Improved JavaScript diff library that returns html/css-formatted new text version with
 * highlighted deletions, insertions, and block moves. It is compatible with all browsers and is
 * not dependent on external libraries.
 *
 * WikEdDiff.php and the JavaScript library wikEd diff are synced one-to-one ports. Changes and
 * fixes are to be applied to both versions.
 *
 * JavaScript library (mirror): https://en.wikipedia.org/wiki/User:Cacycle/diff
 * JavaScript online tool: http://cacycle.altervista.org/wikEd-diff-tool.html
 * MediaWiki extension: https://www.mediawiki.org/wiki/Extension:wikEdDiff
 *
 * This difference engine applies a word-based algorithm that uses unique words as anchor points
 * to identify matching text and moved blocks (Paul Heckel: A technique for isolating differences
 * between files. Communications of the ACM 21(4):264 (1978)).
 *
 * Additional features:
 *
 * - Visual inline style, changes are shown in a single output text
 * - Block move detection and highlighting
 * - Resolution down to characters level
 * - Unicode and multilingual support
 * - Stepwise split (paragraphs, lines, sentences, words, characters)
 * - Recursive diff
 * - Optimized code for resolving unmatched sequences
 * - Minimization of length of moved blocks
 * - Alignment of ambiguous unmatched sequences to next line break or word border
 * - Clipping of unchanged irrelevant parts from the output (optional)
 * - Fully customizable
 * - Text split optimized for MediaWiki source texts
 * - Well commented and documented code
 *
 * Datastructures (abbreviations from publication):
 *
 * class WikEdDiffText:  diff text object (new or old version)
 *   .text                 text of version
 *   .words[]              word count table
 *   .first                index of first token in tokens list
 *   .last                 index of last token in tokens list
 *
 *   .tokens[]:          token list for new or old string (doubly-linked list) (N and O)
 *     .prev               previous list item
 *     .next               next list item
 *     .token              token string
 *     .link               index of corresponding token in new or old text (OA and NA)
 *     .number             list enumeration number
 *     .unique             token is unique word in text
 *
 * class WikEdDiff:      diff object
 *   .config[]:            configuration settings, see top of code for customization options
 *      .regExp[]:            all regular expressions
 *          .split             regular expressions used for splitting text into tokens
 *      .htmlCode            HTML code fragments used for creating the output
 *      .msg                 output messages
 *   .newText              new text
 *   .oldText              old text
 *   .maxWords             word count of longest linked block
 *   .html                 diff html
 *   .error                flag: result has not passed unit tests
 *   .bordersDown[]        linked region borders downwards, [new index, old index]
 *   .bordersUp[]          linked region borders upwards, [new index, old index]
 *   .symbols:             symbols table for whole text at all refinement levels
 *     .token[]              hash table of parsed tokens for passes 1 - 3, points to symbol[i]
 *     .symbol[]:            array of objects that hold token counters and pointers:
 *       .newCount             new text token counter (NC)
 *       .oldCount             old text token counter (OC)
 *       .newToken             token index in text.newText.tokens
 *       .oldToken             token index in text.oldText.tokens
 *     .linked               flag: at least one unique token pair has been linked
 *
 *   .blocks[]:            array, block data (consecutive text tokens) in new text order
 *     .oldBlock             number of block in old text order
 *     .newBlock             number of block in new text order
 *     .oldNumber            old text token number of first token
 *     .newNumber            new text token number of first token
 *     .oldStart             old text token index of first token
 *     .count                number of tokens
 *     .unique               contains unique linked token
 *     .words                word count
 *     .chars                char length
 *     .type                 '=', '-', '+', '|' (same, deletion, insertion, mark)
 *     .section              section number
 *     .group                group number of block
 *     .fixed                belongs to a fixed (not moved) group
 *     .moved                moved block group number corresponding with mark block
 *     .text                 text of block tokens
 *
 *   .sections[]:          array, block sections with no block move crosses outside a section
 *     .blockStart           first block in section
 *     .blockEnd             last block in section

 *   .groups[]:            array, section blocks that are consecutive in old text order
 *     .oldNumber            first block oldNumber
 *     .blockStart           first block index
 *     .blockEnd             last block index
 *     .unique               contains unique linked token
 *     .maxWords             word count of longest linked block
 *     .words                word count
 *     .chars                char count
 *     .fixed                not moved from original position
 *     .movedFrom            group position this group has been moved from
 *     .color                color number of moved group
 *
 *   .fragments[]:         diff fragment list ready for markup, abstraction layer for customization
 *     .text                 block or mark text
 *     .color                moved block or mark color number
 *     .type                 '=', '-', '+'   same, deletion, insertion
 *                           '<', '>'        mark left, mark right
 *                           '(<', '(>', ')' block start and end
 *                           '~', ' ~', '~ ' omission indicators
 *                           '[', ']', ','   fragment start and end, fragment separator
 *                           '{', '}'        container start and end
 *
 */

// JSHint options
/* jshint -W004, -W100, newcap: true, browser: true, jquery: true, sub: true, bitwise: true,
	curly: true, evil: true, forin: true, freeze: true, globalstrict: true, immed: true,
	latedef: true, loopfunc: true, quotmark: single, strict: true, undef: true */
/* global console */

// Turn on ECMAScript 5 strict mode
'use strict';

/** Define global objects. */
var wikEdDiffConfig;
var WED;


/**
 * wikEd diff main class.
 *
 * @class WikEdDiff
 */
var WikEdDiff = function () {

	/** @var array config Configuration and customization settings. */
	this.config = {

		/** Core diff settings (with default values). */

		/**
		 * @var bool config.fullDiff
		 *   Show complete un-clipped diff text (false)
		 */
		'fullDiff': false,

		/**
		 * @var bool config.showBlockMoves
		 *   Enable block move layout with highlighted blocks and marks at the original positions (true)
		 */
		'showBlockMoves': true,

		/**
		 * @var bool config.charDiff
		 *   Enable character-refined diff (true)
		 */
		'charDiff': true,

		/**
		 * @var bool config.repeatedDiff
		 *   Enable repeated diff to resolve problematic sequences (true)
		 */
		'repeatedDiff': true,

		/**
		 * @var bool config.recursiveDiff
		 *   Enable recursive diff to resolve problematic sequences (true)
		 */
		'recursiveDiff': true,

		/**
		 * @var int config.recursionMax
		 *   Maximum recursion depth (10)
		 */
		'recursionMax': 10,

		/**
		 * @var bool config.unlinkBlocks
		 *   Reject blocks if they are too short and their words are not unique,
		 *   prevents fragmentated diffs for very different versions (true)
		 */
		'unlinkBlocks': true,

		/**
		 * @var int config.unlinkMax
		 *   Maximum number of rejection cycles (5)
		 */
		'unlinkMax': 5,

		/**
		 * @var int config.blockMinLength
		 *   Reject blocks if shorter than this number of real words (3)
		 */
		'blockMinLength': 3,

		/**
		 * @var bool config.coloredBlocks
		 *   Display blocks in differing colors (rainbow color scheme) (false)
		 */
		'coloredBlocks': false,

		/**
		 * @var bool config.coloredBlocks
		 *   Do not use UniCode block move marks (legacy browsers) (false)
		 */
		'noUnicodeSymbols': false,

		/**
		 * @var bool config.stripTrailingNewline
		 *   Strip trailing newline off of texts (true in .js, false in .php)
		 */
		'stripTrailingNewline': true,

		/**
		 * @var bool config.debug
		 *   Show debug infos and stats (block, group, and fragment data) in debug console (false)
		 */
		'debug': false,

		/**
		 * @var bool config.timer
		 *   Show timing results in debug console (false)
		 */
		'timer': false,

		/**
		 * @var bool config.unitTesting
		 *   Run unit tests to prove correct working, display results in debug console (false)
		 */
		'unitTesting': false,

		/** RegExp character classes. */

		// UniCode letter support for regexps
		// From http://xregexp.com/addons/unicode/unicode-base.js v1.0.0
		'regExpLetters':
			'a-zA-Z0-9' + (
				'00AA00B500BA00C0-00D600D8-00F600F8-02C102C6-02D102E0-02E402EC02EE0370-037403760377037A-' +
				'037D03860388-038A038C038E-03A103A3-03F503F7-0481048A-05270531-055605590561-058705D0-05EA' +
				'05F0-05F20620-064A066E066F0671-06D306D506E506E606EE06EF06FA-06FC06FF07100712-072F074D-' +
				'07A507B107CA-07EA07F407F507FA0800-0815081A082408280840-085808A008A2-08AC0904-0939093D' +
				'09500958-09610971-09770979-097F0985-098C098F09900993-09A809AA-09B009B209B6-09B909BD09CE' +
				'09DC09DD09DF-09E109F009F10A05-0A0A0A0F0A100A13-0A280A2A-0A300A320A330A350A360A380A39' +
				'0A59-0A5C0A5E0A72-0A740A85-0A8D0A8F-0A910A93-0AA80AAA-0AB00AB20AB30AB5-0AB90ABD0AD00AE0' +
				'0AE10B05-0B0C0B0F0B100B13-0B280B2A-0B300B320B330B35-0B390B3D0B5C0B5D0B5F-0B610B710B83' +
				'0B85-0B8A0B8E-0B900B92-0B950B990B9A0B9C0B9E0B9F0BA30BA40BA8-0BAA0BAE-0BB90BD00C05-0C0C' +
				'0C0E-0C100C12-0C280C2A-0C330C35-0C390C3D0C580C590C600C610C85-0C8C0C8E-0C900C92-0CA80CAA-' +
				'0CB30CB5-0CB90CBD0CDE0CE00CE10CF10CF20D05-0D0C0D0E-0D100D12-0D3A0D3D0D4E0D600D610D7A-' +
				'0D7F0D85-0D960D9A-0DB10DB3-0DBB0DBD0DC0-0DC60E01-0E300E320E330E40-0E460E810E820E840E87' +
				'0E880E8A0E8D0E94-0E970E99-0E9F0EA1-0EA30EA50EA70EAA0EAB0EAD-0EB00EB20EB30EBD0EC0-0EC4' +
				'0EC60EDC-0EDF0F000F40-0F470F49-0F6C0F88-0F8C1000-102A103F1050-1055105A-105D106110651066' +
				'106E-10701075-1081108E10A0-10C510C710CD10D0-10FA10FC-1248124A-124D1250-12561258125A-125D' +
				'1260-1288128A-128D1290-12B012B2-12B512B8-12BE12C012C2-12C512C8-12D612D8-13101312-1315' +
				'1318-135A1380-138F13A0-13F41401-166C166F-167F1681-169A16A0-16EA1700-170C170E-17111720-' +
				'17311740-17511760-176C176E-17701780-17B317D717DC1820-18771880-18A818AA18B0-18F51900-191C' +
				'1950-196D1970-19741980-19AB19C1-19C71A00-1A161A20-1A541AA71B05-1B331B45-1B4B1B83-1BA0' +
				'1BAE1BAF1BBA-1BE51C00-1C231C4D-1C4F1C5A-1C7D1CE9-1CEC1CEE-1CF11CF51CF61D00-1DBF1E00-1F15' +
				'1F18-1F1D1F20-1F451F48-1F4D1F50-1F571F591F5B1F5D1F5F-1F7D1F80-1FB41FB6-1FBC1FBE1FC2-1FC4' +
				'1FC6-1FCC1FD0-1FD31FD6-1FDB1FE0-1FEC1FF2-1FF41FF6-1FFC2071207F2090-209C21022107210A-2113' +
				'21152119-211D212421262128212A-212D212F-2139213C-213F2145-2149214E218321842C00-2C2E2C30-' +
				'2C5E2C60-2CE42CEB-2CEE2CF22CF32D00-2D252D272D2D2D30-2D672D6F2D80-2D962DA0-2DA62DA8-2DAE' +
				'2DB0-2DB62DB8-2DBE2DC0-2DC62DC8-2DCE2DD0-2DD62DD8-2DDE2E2F300530063031-3035303B303C3041-' +
				'3096309D-309F30A1-30FA30FC-30FF3105-312D3131-318E31A0-31BA31F0-31FF3400-4DB54E00-9FCC' +
				'A000-A48CA4D0-A4FDA500-A60CA610-A61FA62AA62BA640-A66EA67F-A697A6A0-A6E5A717-A71FA722-' +
				'A788A78B-A78EA790-A793A7A0-A7AAA7F8-A801A803-A805A807-A80AA80C-A822A840-A873A882-A8B3' +
				'A8F2-A8F7A8FBA90A-A925A930-A946A960-A97CA984-A9B2A9CFAA00-AA28AA40-AA42AA44-AA4BAA60-' +
				'AA76AA7AAA80-AAAFAAB1AAB5AAB6AAB9-AABDAAC0AAC2AADB-AADDAAE0-AAEAAAF2-AAF4AB01-AB06AB09-' +
				'AB0EAB11-AB16AB20-AB26AB28-AB2EABC0-ABE2AC00-D7A3D7B0-D7C6D7CB-D7FBF900-FA6DFA70-FAD9' +
				'FB00-FB06FB13-FB17FB1DFB1F-FB28FB2A-FB36FB38-FB3CFB3EFB40FB41FB43FB44FB46-FBB1FBD3-FD3D' +
				'FD50-FD8FFD92-FDC7FDF0-FDFBFE70-FE74FE76-FEFCFF21-FF3AFF41-FF5AFF66-FFBEFFC2-FFC7FFCA-' +
				'FFCFFFD2-FFD7FFDA-FFDC'
			).replace( /(\w{4})/g, '\\u$1' ),

		// New line characters without and with \n and \r
		'regExpNewLines': '\\u0085\\u2028',
		'regExpNewLinesAll': '\\n\\r\\u0085\\u2028',

		// Breaking white space characters without \n, \r, and \f
		'regExpBlanks': ' \\t\\x0b\\u2000-\\u200b\\u202f\\u205f\\u3000',

		// Full stops without '.'
		'regExpFullStops':
			'\\u0589\\u06D4\\u0701\\u0702\\u0964\\u0DF4\\u1362\\u166E\\u1803\\u1809' +
			'\\u2CF9\\u2CFE\\u2E3C\\u3002\\uA4FF\\uA60E\\uA6F3\\uFE52\\uFF0E\\uFF61',

		// New paragraph characters without \n and \r
		'regExpNewParagraph': '\\f\\u2029',

		// Exclamation marks without '!'
		'regExpExclamationMarks':
			'\\u01C3\\u01C3\\u01C3\\u055C\\u055C\\u07F9\\u1944\\u1944' +
			'\\u203C\\u203C\\u2048\\u2048\\uFE15\\uFE57\\uFF01',

		// Question marks without '?'
		'regExpQuestionMarks':
			'\\u037E\\u055E\\u061F\\u1367\\u1945\\u2047\\u2049' +
			'\\u2CFA\\u2CFB\\u2E2E\\uA60F\\uA6F7\\uFE56\\uFF1F',

		/** Clip settings. */

		// Find clip position: characters from right
		'clipHeadingLeft':      1500,
		'clipParagraphLeftMax': 1500,
		'clipParagraphLeftMin':  500,
		'clipLineLeftMax':      1000,
		'clipLineLeftMin':       500,
		'clipBlankLeftMax':     1000,
		'clipBlankLeftMin':      500,
		'clipCharsLeft':         500,

		// Find clip position: characters from right
		'clipHeadingRight':      1500,
		'clipParagraphRightMax': 1500,
		'clipParagraphRightMin':  500,
		'clipLineRightMax':      1000,
		'clipLineRightMin':       500,
		'clipBlankRightMax':     1000,
		'clipBlankRightMin':      500,
		'clipCharsRight':         500,

		// Maximum number of lines to search for clip position
		'clipLinesRightMax': 10,
		'clipLinesLeftMax': 10,

		// Skip clipping if ranges are too close
		'clipSkipLines': 5,
		'clipSkipChars': 1000,

		// Css stylesheet
		'cssMarkLeft': '◀',
		'cssMarkRight': '▶',
		'stylesheet':

			// Insert
			'.wikEdDiffInsert {' +
			'font-weight: bold; background-color: #bbddff; ' +
			'color: #222; border-radius: 0.25em; padding: 0.2em 1px; ' +
			'} ' +
			'.wikEdDiffInsertBlank { background-color: #66bbff; } ' +
			'.wikEdDiffFragment:hover .wikEdDiffInsertBlank { background-color: #bbddff; } ' +

			// Delete
			'.wikEdDiffDelete {' +
			'font-weight: bold; background-color: #ffe49c; ' +
			'color: #222; border-radius: 0.25em; padding: 0.2em 1px; ' +
			'} ' +
			'.wikEdDiffDeleteBlank { background-color: #ffd064; } ' +
			'.wikEdDiffFragment:hover .wikEdDiffDeleteBlank { background-color: #ffe49c; } ' +

			// Block
			'.wikEdDiffBlock {' +
			'font-weight: bold; background-color: #e8e8e8; ' +
			'border-radius: 0.25em; padding: 0.2em 1px; margin: 0 1px; ' +
			'} ' +
			'.wikEdDiffBlock { } ' +
			'.wikEdDiffBlock0 { background-color: #ffff80; } ' +
			'.wikEdDiffBlock1 { background-color: #d0ff80; } ' +
			'.wikEdDiffBlock2 { background-color: #ffd8f0; } ' +
			'.wikEdDiffBlock3 { background-color: #c0ffff; } ' +
			'.wikEdDiffBlock4 { background-color: #fff888; } ' +
			'.wikEdDiffBlock5 { background-color: #bbccff; } ' +
			'.wikEdDiffBlock6 { background-color: #e8c8ff; } ' +
			'.wikEdDiffBlock7 { background-color: #ffbbbb; } ' +
			'.wikEdDiffBlock8 { background-color: #a0e8a0; } ' +
			'.wikEdDiffBlockHighlight {' +
			'background-color: #777; color: #fff; ' +
			'border: solid #777; border-width: 1px 0; ' +
			'} ' +

			// Mark
			'.wikEdDiffMarkLeft, .wikEdDiffMarkRight {' +
			'font-weight: bold; background-color: #ffe49c; ' +
			'color: #666; border-radius: 0.25em; padding: 0.2em; margin: 0 1px; ' +
			'} ' +
			'.wikEdDiffMarkLeft:before { content: "{cssMarkLeft}"; } ' +
			'.wikEdDiffMarkRight:before { content: "{cssMarkRight}"; } ' +
			'.wikEdDiffMarkLeft.wikEdDiffNoUnicode:before { content: "<"; } ' +
			'.wikEdDiffMarkRight.wikEdDiffNoUnicode:before { content: ">"; } ' +
			'.wikEdDiffMark { background-color: #e8e8e8; color: #666; } ' +
			'.wikEdDiffMark0 { background-color: #ffff60; } ' +
			'.wikEdDiffMark1 { background-color: #c8f880; } ' +
			'.wikEdDiffMark2 { background-color: #ffd0f0; } ' +
			'.wikEdDiffMark3 { background-color: #a0ffff; } ' +
			'.wikEdDiffMark4 { background-color: #fff860; } ' +
			'.wikEdDiffMark5 { background-color: #b0c0ff; } ' +
			'.wikEdDiffMark6 { background-color: #e0c0ff; } ' +
			'.wikEdDiffMark7 { background-color: #ffa8a8; } ' +
			'.wikEdDiffMark8 { background-color: #98e898; } ' +
			'.wikEdDiffMarkHighlight { background-color: #777; color: #fff; } ' +

			// Wrappers
			'.wikEdDiffContainer { } ' +
			'.wikEdDiffFragment {' +
			'white-space: pre-wrap; background: #fff; border: #bbb solid; ' +
			'border-width: 1px 1px 1px 0.5em; border-radius: 0.5em; font-family: sans-serif; ' +
			'font-size: 88%; line-height: 1.6; box-shadow: 2px 2px 2px #ddd; padding: 1em; margin: 0; ' +
			'} ' +
			'.wikEdDiffNoChange { background: #f0f0f0; border: 1px #bbb solid; border-radius: 0.5em; ' +
			'line-height: 1.6; box-shadow: 2px 2px 2px #ddd; padding: 0.5em; margin: 1em 0; ' +
			'text-align: center; ' +
			'} ' +
			'.wikEdDiffSeparator { margin-bottom: 1em; } ' +
			'.wikEdDiffOmittedChars { } ' +

			// Newline
			'.wikEdDiffNewline:before { content: "¶"; color: transparent; } ' +
			'.wikEdDiffBlock:hover .wikEdDiffNewline:before { color: #aaa; } ' +
			'.wikEdDiffBlockHighlight .wikEdDiffNewline:before { color: transparent; } ' +
			'.wikEdDiffBlockHighlight:hover .wikEdDiffNewline:before { color: #ccc; } ' +
			'.wikEdDiffBlockHighlight:hover .wikEdDiffInsert .wikEdDiffNewline:before, ' +
			'.wikEdDiffInsert:hover .wikEdDiffNewline:before' +
			'{ color: #999; } ' +
			'.wikEdDiffBlockHighlight:hover .wikEdDiffDelete .wikEdDiffNewline:before, ' +
			'.wikEdDiffDelete:hover .wikEdDiffNewline:before' +
			'{ color: #aaa; } ' +

			// Tab
			'.wikEdDiffTab { position: relative; } ' +
			'.wikEdDiffTabSymbol { position: absolute; top: -0.2em; } ' +
			'.wikEdDiffTabSymbol:before { content: "→"; font-size: smaller; color: #ccc; } ' +
			'.wikEdDiffBlock .wikEdDiffTabSymbol:before { color: #aaa; } ' +
			'.wikEdDiffBlockHighlight .wikEdDiffTabSymbol:before { color: #aaa; } ' +
			'.wikEdDiffInsert .wikEdDiffTabSymbol:before { color: #aaa; } ' +
			'.wikEdDiffDelete .wikEdDiffTabSymbol:before { color: #bbb; } ' +

			// Space
			'.wikEdDiffSpace { position: relative; } ' +
			'.wikEdDiffSpaceSymbol { position: absolute; top: -0.2em; left: -0.05em; } ' +
			'.wikEdDiffSpaceSymbol:before { content: "·"; color: transparent; } ' +
			'.wikEdDiffBlock:hover .wikEdDiffSpaceSymbol:before { color: #999; } ' +
			'.wikEdDiffBlockHighlight .wikEdDiffSpaceSymbol:before { color: transparent; } ' +
			'.wikEdDiffBlockHighlight:hover .wikEdDiffSpaceSymbol:before { color: #ddd; } ' +
			'.wikEdDiffBlockHighlight:hover .wikEdDiffInsert .wikEdDiffSpaceSymbol:before,' +
			'.wikEdDiffInsert:hover .wikEdDiffSpaceSymbol:before ' +
			'{ color: #888; } ' +
			'.wikEdDiffBlockHighlight:hover .wikEdDiffDelete .wikEdDiffSpaceSymbol:before,' +
			'.wikEdDiffDelete:hover .wikEdDiffSpaceSymbol:before ' +
			'{ color: #999; } ' +

			// Error
			'.wikEdDiffError .wikEdDiffFragment,' +
			'.wikEdDiffError .wikEdDiffNoChange' +
			'{ background: #faa; }'
	};

	/** Add regular expressions to configuration settings. */

	this.config.regExp = {

		// RegExps for splitting text
		'split': {

			// Split into paragraphs, after double newlines
			'paragraph': new RegExp(
				'(\\r\\n|\\n|\\r){2,}|[' +
				this.config.regExpNewParagraph +
				']',
				'g'
			),

			// Split into lines
			'line': new RegExp(
				'\\r\\n|\\n|\\r|[' +
				this.config.regExpNewLinesAll +
				']',
				'g'
			),

			// Split into sentences /[^ ].*?[.!?:;]+(?= |$)/
			'sentence': new RegExp(
				'[^' +
				this.config.regExpBlanks +
				'].*?[.!?:;' +
				this.config.regExpFullStops +
				this.config.regExpExclamationMarks +
				this.config.regExpQuestionMarks +
				']+(?=[' +
				this.config.regExpBlanks +
				']|$)',
				'g'
			),

			// Split into inline chunks
			'chunk': new RegExp(
				'\\[\\[[^\\[\\]\\n]+\\]\\]|' +       // [[wiki link]]
				'\\{\\{[^\\{\\}\\n]+\\}\\}|' +       // {{template}}
				'\\[[^\\[\\]\\n]+\\]|' +             // [ext. link]
				'<\\/?[^<>\\[\\]\\{\\}\\n]+>|' +     // <html>
				'\\[\\[[^\\[\\]\\|\\n]+\\]\\]\\||' + // [[wiki link|
				'\\{\\{[^\\{\\}\\|\\n]+\\||' +       // {{template|
				'\\b((https?:|)\\/\\/)[^\\x00-\\x20\\s"\\[\\]\\x7f]+', // link
				'g'
			),

			// Split into words, multi-char markup, and chars
			// regExpLetters speed-up: \\w+
			'word': new RegExp(
				'(\\w+|[_' +
				this.config.regExpLetters +
				'])+([\'’][_' +
				this.config.regExpLetters +
				']*)*|\\[\\[|\\]\\]|\\{\\{|\\}\\}|&\\w+;|\'\'\'|\'\'|==+|\\{\\||\\|\\}|\\|-|.',
				'g'
			),

			// Split into chars
			'character': /./g
		},

		// RegExp to detect blank tokens
		'blankOnlyToken': new RegExp(
			'[^' +
			this.config.regExpBlanks +
			this.config.regExpNewLinesAll +
			this.config.regExpNewParagraph +
			']'
		),

		// RegExps for sliding gaps: newlines and space/word breaks
		'slideStop': new RegExp(
			'[' +
			this.config.regExpNewLinesAll +
			this.config.regExpNewParagraph +
			']$'
		),
		'slideBorder': new RegExp(
			'[' +
			this.config.regExpBlanks +
			']$'
		),

		// RegExps for counting words
		'countWords': new RegExp(
			'(\\w+|[_' +
			this.config.regExpLetters +
			'])+([\'’][_' +
			this.config.regExpLetters +
			']*)*',
			'g'
		),
		'countChunks': new RegExp(
			'\\[\\[[^\\[\\]\\n]+\\]\\]|' +       // [[wiki link]]
			'\\{\\{[^\\{\\}\\n]+\\}\\}|' +       // {{template}}
			'\\[[^\\[\\]\\n]+\\]|' +             // [ext. link]
			'<\\/?[^<>\\[\\]\\{\\}\\n]+>|' +     // <html>
			'\\[\\[[^\\[\\]\\|\\n]+\\]\\]\\||' + // [[wiki link|
			'\\{\\{[^\\{\\}\\|\\n]+\\||' +       // {{template|
			'\\b((https?:|)\\/\\/)[^\\x00-\\x20\\s"\\[\\]\\x7f]+', // link
			'g'
		),

		// RegExp detecting blank-only and single-char blocks
		'blankBlock': /^([^\t\S]+|[^\t])$/,

		// RegExps for clipping
		'clipLine': new RegExp(
			'[' + this.config.regExpNewLinesAll +
			this.config.regExpNewParagraph +
			']+',
			'g'
		),
		'clipHeading': new RegExp(
			'( ^|\\n)(==+.+?==+|\\{\\||\\|\\}).*?(?=\\n|$)', 'g' ),
		'clipParagraph': new RegExp(
			'( (\\r\\n|\\n|\\r){2,}|[' +
			this.config.regExpNewParagraph +
			'])+',
			'g'
		),
		'clipBlank': new RegExp(
			'[' +
			this.config.regExpBlanks + ']+',
			'g'
		),
		'clipTrimNewLinesLeft': new RegExp(
			'[' +
			this.config.regExpNewLinesAll +
			this.config.regExpNewParagraph +
			']+$',
			'g'
		),
		'clipTrimNewLinesRight': new RegExp(
			'^[' +
			this.config.regExpNewLinesAll +
			this.config.regExpNewParagraph +
			']+',
			'g'
		),
		'clipTrimBlanksLeft': new RegExp(
			'[' +
			this.config.regExpBlanks +
			this.config.regExpNewLinesAll +
			this.config.regExpNewParagraph +
			']+$',
			'g'
		),
		'clipTrimBlanksRight': new RegExp(
			'^[' +
			this.config.regExpBlanks +
			this.config.regExpNewLinesAll +
			this.config.regExpNewParagraph +
			']+',
			'g'
		)
	};

	/** Add messages to configuration settings. */

	this.config.msg = {
    'wiked-diff-empty': '(No difference)',
    'wiked-diff-same':  '=',
    'wiked-diff-ins':   '+',
    'wiked-diff-del':   '-',
    'wiked-diff-block-left':  '◀',
    'wiked-diff-block-right': '▶',
    'wiked-diff-block-left-nounicode':  '<',
    'wiked-diff-block-right-nounicode': '>',
		'wiked-diff-error': 'Error: diff not consistent with versions!'
	};

	/**
	 * Add output html fragments to configuration settings.
	 * Dynamic replacements:
	 *   {number}: class/color/block/mark/id number
	 *   {title}: title attribute (popup)
	 *   {nounicode}: noUnicodeSymbols fallback
	 */
	this.config.htmlCode = {
		'noChangeStart':
			'<div class="wikEdDiffNoChange" title="' +
			this.config.msg['wiked-diff-same'] +
			'">',
		'noChangeEnd': '</div>',

		'containerStart': '<div class="wikEdDiffContainer" id="wikEdDiffContainer">',
		'containerEnd': '</div>',

		'fragmentStart': '<pre class="wikEdDiffFragment" style="white-space: pre-wrap;">',
		'fragmentEnd': '</pre>',
		'separator': '<div class="wikEdDiffSeparator"></div>',

		'insertStart':
			'<span class="wikEdDiffInsert" title="' +
			this.config.msg['wiked-diff-ins'] +
			'">',
		'insertStartBlank':
			'<span class="wikEdDiffInsert wikEdDiffInsertBlank" title="' +
			this.config.msg['wiked-diff-ins'] +
			'">',
		'insertEnd': '</span>',

		'deleteStart':
			'<span class="wikEdDiffDelete" title="' +
			this.config.msg['wiked-diff-del'] +
			'">',
		'deleteStartBlank':
			'<span class="wikEdDiffDelete wikEdDiffDeleteBlank" title="' +
			this.config.msg['wiked-diff-del'] +
			'">',
		'deleteEnd': '</span>',

		'blockStart':
			'<span class="wikEdDiffBlock"' +
			'title="{title}" id="wikEdDiffBlock{number}"' +
			'onmouseover="wikEdDiffBlockHandler(undefined, this, \'mouseover\');">',
		'blockColoredStart':
			'<span class="wikEdDiffBlock wikEdDiffBlock wikEdDiffBlock{number}"' +
			'title="{title}" id="wikEdDiffBlock{number}"' +
			'onmouseover="wikEdDiffBlockHandler(undefined, this, \'mouseover\');">',
		'blockEnd': '</span>',

		'markLeft':
			'<span class="wikEdDiffMarkLeft{nounicode}"' +
			'title="{title}" id="wikEdDiffMark{number}"' +
			'onmouseover="wikEdDiffBlockHandler(undefined, this, \'mouseover\');"></span>',
		'markLeftColored':
			'<span class="wikEdDiffMarkLeft{nounicode} wikEdDiffMark wikEdDiffMark{number}"' +
			'title="{title}" id="wikEdDiffMark{number}"' +
			'onmouseover="wikEdDiffBlockHandler(undefined, this, \'mouseover\');"></span>',

		'markRight':
			'<span class="wikEdDiffMarkRight{nounicode}"' +
			'title="{title}" id="wikEdDiffMark{number}"' +
			'onmouseover="wikEdDiffBlockHandler(undefined, this, \'mouseover\');"></span>',
		'markRightColored':
			'<span class="wikEdDiffMarkRight{nounicode} wikEdDiffMark wikEdDiffMark{number}"' +
			'title="{title}" id="wikEdDiffMark{number}"' +
			'onmouseover="wikEdDiffBlockHandler(undefined, this, \'mouseover\');"></span>',

		'newline': '<span class="wikEdDiffNewline">\n</span>',
		'tab': '<span class="wikEdDiffTab"><span class="wikEdDiffTabSymbol"></span>\t</span>',
		'space': '<span class="wikEdDiffSpace"><span class="wikEdDiffSpaceSymbol"></span> </span>',

		'omittedChars': '<span class="wikEdDiffOmittedChars">…</span>',

		'errorStart': '<div class="wikEdDiffError" title="Error: diff not consistent with versions!">',
		'errorEnd': '</div>'
	};

	/*
	 * Add JavaScript event handler function to configuration settings
	 * Highlights corresponding block and mark elements on hover and jumps between them on click
	 * Code for use in non-jQuery environments and legacy browsers (at least IE 8 compatible)
	 *
	 * @option Event|undefined event Browser event if available
	 * @option element Node DOM node
	 * @option type string Event type
	 */
	this.config.blockHandler = function ( event, element, type ) {

		// IE compatibility
		if ( event === undefined && window.event !== undefined ) {
			event = window.event;
		}

		// Get mark/block elements
		var number = element.id.replace( /\D/g, '' );
		var block = document.getElementById( 'wikEdDiffBlock' + number );
		var mark = document.getElementById( 'wikEdDiffMark' + number );
		if ( block === null || mark === null ) {
			return;
		}

		// Highlight corresponding mark/block pairs
		if ( type === 'mouseover' ) {
			element.onmouseover = null;
			element.onmouseout = function ( event ) {
				window.wikEdDiffBlockHandler( event, element, 'mouseout' );
			};
			element.onclick = function ( event ) {
				window.wikEdDiffBlockHandler( event, element, 'click' );
			};
			block.className += ' wikEdDiffBlockHighlight';
			mark.className += ' wikEdDiffMarkHighlight';
		}

		// Remove mark/block highlighting
		if ( type === 'mouseout' || type === 'click' ) {
			element.onmouseout = null;
			element.onmouseover = function ( event ) {
				window.wikEdDiffBlockHandler( event, element, 'mouseover' );
			};

			// Reset, allow outside container (e.g. legend)
			if ( type !== 'click' ) {
				block.className = block.className.replace( / wikEdDiffBlockHighlight/g, '' );
				mark.className = mark.className.replace( / wikEdDiffMarkHighlight/g, '' );

				// GetElementsByClassName
				var container = document.getElementById( 'wikEdDiffContainer' );
				if ( container !== null ) {
					var spans = container.getElementsByTagName( 'span' );
					var spansLength = spans.length;
					for ( var i = 0; i < spansLength; i ++ ) {
						if ( spans[i] !== block && spans[i] !== mark ) {
							if ( spans[i].className.indexOf( ' wikEdDiffBlockHighlight' ) !== -1 ) {
								spans[i].className = spans[i].className.replace( / wikEdDiffBlockHighlight/g, '' );
							}
							else if ( spans[i].className.indexOf( ' wikEdDiffMarkHighlight') !== -1 ) {
								spans[i].className = spans[i].className.replace( / wikEdDiffMarkHighlight/g, '' );
							}
						}
					}
				}
			}
		}

		// Scroll to corresponding mark/block element
		if ( type === 'click' ) {

			// Get corresponding element
			var corrElement;
			if ( element === block ) {
				corrElement = mark;
			}
			else {
				corrElement = block;
			}

			// Get element height (getOffsetTop)
			var corrElementPos = 0;
			var node = corrElement;
			do {
				corrElementPos += node.offsetTop;
			} while ( ( node = node.offsetParent ) !== null );

			// Get scroll height
			var top;
			if ( window.pageYOffset !== undefined ) {
				top = window.pageYOffset;
			}
			else {
				top = document.documentElement.scrollTop;
			}

			// Get cursor pos
			var cursor;
			if ( event.pageY !== undefined ) {
				cursor = event.pageY;
			}
			else if ( event.clientY !== undefined ) {
				cursor = event.clientY + top;
			}

			// Get line height
			var line = 12;
			if ( window.getComputedStyle !== undefined ) {
				line = parseInt( window.getComputedStyle( corrElement ).getPropertyValue( 'line-height' ) );
			}

			// Scroll element under mouse cursor
			window.scroll( 0, corrElementPos + top - cursor + line / 2 );
		}
		return;
	};

	/** Internal data structures. */

	/** @var WikEdDiffText newText New text version object with text and token list */
	this.newText = null;

	/** @var WikEdDiffText oldText Old text version object with text and token list */
	this.oldText = null;

	/** @var object symbols Symbols table for whole text at all refinement levels */
	this.symbols = {
		token: [],
		hashTable: {},
		linked: false
	};

	/** @var array bordersDown Matched region borders downwards */
	this.bordersDown = [];

	/** @var array bordersUp Matched region borders upwards */
	this.bordersUp = [];

	/** @var array blocks Block data (consecutive text tokens) in new text order */
	this.blocks = [];

	/** @var int maxWords Maximal detected word count of all linked blocks */
	this.maxWords = 0;

	/** @var array groups Section blocks that are consecutive in old text order */
	this.groups = [];

	/** @var array sections Block sections with no block move crosses outside a section */
	this.sections = [];

	/** @var object timer Debug timer array: string 'label' => float milliseconds. */
	this.timer = {};

	/** @var array recursionTimer Count time spent in recursion level in milliseconds. */
	this.recursionTimer = [];

	/** Output data. */

	/** @var bool error Unit tests have detected a diff error */
	this.error = false;

	/** @var array fragments Diff fragment list for markup, abstraction layer for customization */
	this.fragments = [];

	/** @var string html Html code of diff */
	this.html = '';


	/**
	 * Constructor, initialize settings, load js and css.
	 *
	 * @param[in] object wikEdDiffConfig Custom customization settings
	 * @param[out] object config Settings
	 */

	this.init = function () {

		// Import customizations from wikEdDiffConfig{}
		if ( typeof wikEdDiffConfig === 'object' ) {
			this.deepCopy( wikEdDiffConfig, this.config );
		}

		// Add CSS stylescheet
		this.addStyleSheet( this.config.stylesheet );

		// Load block handler script
		if ( this.config.showBlockMoves === true ) {

			// Add block handler to head if running under Greasemonkey
			if ( typeof GM_info === 'object' ) {
				var script = 'var wikEdDiffBlockHandler = ' + this.config.blockHandler.toString() + ';';
				this.addScript( script );
			}
			else {
				window.wikEdDiffBlockHandler = this.config.blockHandler;
			}
		}
		return;
	};


	/**
	 * Main diff method.
	 *
	 * @param string oldString Old text version
	 * @param string newString New text version
	 * @param[out] array fragment
	 *   Diff fragment list ready for markup, abstraction layer for customized diffs
	 * @param[out] string html Html code of diff
	 * @return string Html code of diff
	 */
	this.diff = function ( oldString, newString ) {

		// Start total timer
		if ( this.config.timer === true ) {
			this.time( 'total' );
		}

		// Start diff timer
		if ( this.config.timer === true ) {
			this.time( 'diff' );
		}

		// Reset error flag
		this.error = false;

		// Strip trailing newline (.js only)
		if ( this.config.stripTrailingNewline === true ) {
			if ( newString.substr( -1 ) === '\n' && oldString.substr( -1 === '\n' ) ) {
				newString = newString.substr( 0, newString.length - 1 );
				oldString = oldString.substr( 0, oldString.length - 1 );
			}
		}

		// Load version strings into WikEdDiffText objects
		this.newText = new WikEdDiff.WikEdDiffText( newString, this );
		this.oldText = new WikEdDiff.WikEdDiffText( oldString, this );

		// Trap trivial changes: no change
		if ( this.newText.text === this.oldText.text ) {
			this.html =
				this.config.htmlCode.containerStart +
				this.config.htmlCode.noChangeStart +
				this.htmlEscape( this.config.msg['wiked-diff-empty'] ) +
				this.config.htmlCode.noChangeEnd +
				this.config.htmlCode.containerEnd;
			return this.html;
		}

		// Trap trivial changes: old text deleted
		if (
			this.oldText.text === '' || (
				this.oldText.text === '\n' &&
				( this.newText.text.charAt( this.newText.text.length - 1 ) === '\n' )
			)
		) {
			this.html =
				this.config.htmlCode.containerStart +
				this.config.htmlCode.fragmentStart +
				this.config.htmlCode.insertStart +
				this.htmlEscape( this.newText.text ) +
				this.config.htmlCode.insertEnd +
				this.config.htmlCode.fragmentEnd +
				this.config.htmlCode.containerEnd;
			return this.html;
		}

		// Trap trivial changes: new text deleted
		if (
			this.newText.text === '' || (
				this.newText.text === '\n' &&
				( this.oldText.text.charAt( this.oldText.text.length - 1 ) === '\n' )
			)
		) {
			this.html =
				this.config.htmlCode.containerStart +
				this.config.htmlCode.fragmentStart +
				this.config.htmlCode.deleteStart +
				this.htmlEscape( this.oldText.text ) +
				this.config.htmlCode.deleteEnd +
				this.config.htmlCode.fragmentEnd +
				this.config.htmlCode.containerEnd;
			return this.html;
		}

		// Split new and old text into paragraps
		if ( this.config.timer === true ) {
			this.time( 'paragraph split' );
		}
		this.newText.splitText( 'paragraph' );
		this.oldText.splitText( 'paragraph' );
		if ( this.config.timer === true ) {
			this.timeEnd( 'paragraph split' );
		}

		// Calculate diff
		this.calculateDiff( 'line' );

		// Refine different paragraphs into lines
		if ( this.config.timer === true ) {
			this.time( 'line split' );
		}
		this.newText.splitRefine( 'line' );
		this.oldText.splitRefine( 'line' );
		if ( this.config.timer === true ) {
			this.timeEnd( 'line split' );
		}

		// Calculate refined diff
		this.calculateDiff( 'line' );

		// Refine different lines into sentences
		if ( this.config.timer === true ) {
			this.time( 'sentence split' );
		}
		this.newText.splitRefine( 'sentence' );
		this.oldText.splitRefine( 'sentence' );
		if ( this.config.timer === true ) {
			this.timeEnd( 'sentence split' );
		}

		// Calculate refined diff
		this.calculateDiff( 'sentence' );

		// Refine different sentences into chunks
		if ( this.config.timer === true ) {
			this.time( 'chunk split' );
		}
		this.newText.splitRefine( 'chunk' );
		this.oldText.splitRefine( 'chunk' );
		if ( this.config.timer === true ) {
			this.timeEnd( 'chunk split' );
		}

		// Calculate refined diff
		this.calculateDiff( 'chunk' );

		// Refine different chunks into words
		if ( this.config.timer === true ) {
			this.time( 'word split' );
		}
		this.newText.splitRefine( 'word' );
		this.oldText.splitRefine( 'word' );
		if ( this.config.timer === true ) {
			this.timeEnd( 'word split' );
		}

		// Calculate refined diff information with recursion for unresolved gaps
		this.calculateDiff( 'word', true );

		// Slide gaps
		if ( this.config.timer === true ) {
			this.time( 'word slide' );
		}
		this.slideGaps( this.newText, this.oldText );
		this.slideGaps( this.oldText, this.newText );
		if ( this.config.timer === true ) {
			this.timeEnd( 'word slide' );
		}

		// Split tokens into chars
		if ( this.config.charDiff === true ) {

			// Split tokens into chars in selected unresolved gaps
			if ( this.config.timer === true ) {
				this.time( 'character split' );
			}
			this.splitRefineChars();
			if ( this.config.timer === true ) {
				this.timeEnd( 'character split' );
			}

			// Calculate refined diff information with recursion for unresolved gaps
			this.calculateDiff( 'character', true );

			// Slide gaps
			if ( this.config.timer === true ) {
				this.time( 'character slide' );
			}
			this.slideGaps( this.newText, this.oldText );
			this.slideGaps( this.oldText, this.newText );
			if ( this.config.timer === true ) {
				this.timeEnd( 'character slide' );
			}
		}

		// Free memory
		this.symbols = undefined;
		this.bordersDown = undefined;
		this.bordersUp = undefined;
		this.newText.words = undefined;
		this.oldText.words = undefined;

		// Enumerate token lists
		this.newText.enumerateTokens();
		this.oldText.enumerateTokens();

		// Detect moved blocks
		if ( this.config.timer === true ) {
			this.time( 'blocks' );
		}
		this.detectBlocks();
		if ( this.config.timer === true ) {
			this.timeEnd( 'blocks' );
		}

		// Free memory
		this.newText.tokens = undefined;
		this.oldText.tokens = undefined;

		// Assemble blocks into fragment table
		this.getDiffFragments();

		// Free memory
		this.blocks = undefined;
		this.groups = undefined;
		this.sections = undefined;

		// Stop diff timer
		if ( this.config.timer === true ) {
			this.timeEnd( 'diff' );
		}

		// Unit tests
		if ( this.config.unitTesting === true ) {

			// Test diff to test consistency between input and output
			if ( this.config.timer === true ) {
				this.time( 'unit tests' );
			}
			this.unitTests();
			if ( this.config.timer === true ) {
				this.timeEnd( 'unit tests' );
			}
		}

		// Clipping
		if ( this.config.fullDiff === false ) {

			// Clipping unchanged sections from unmoved block text
			if ( this.config.timer === true ) {
				this.time( 'clip' );
			}
			this.clipDiffFragments();
			if ( this.config.timer === true ) {
				this.timeEnd( 'clip' );
			}
		}

		// Create html formatted diff code from diff fragments
		if ( this.config.timer === true ) {
			this.time( 'html' );
		}
		this.getDiffHtml();
		if ( this.config.timer === true ) {
			this.timeEnd( 'html' );
		}

		// No change
		if ( this.html === '' ) {
			this.html =
				this.config.htmlCode.containerStart +
				this.config.htmlCode.noChangeStart +
				this.htmlEscape( this.config.msg['wiked-diff-empty'] ) +
				this.config.htmlCode.noChangeEnd +
				this.config.htmlCode.containerEnd;
		}

		// Add error indicator
		if ( this.error === true ) {
			this.html = this.config.htmlCode.errorStart + this.html + this.config.htmlCode.errorEnd;
		}

		// Stop total timer
		if ( this.config.timer === true ) {
			this.timeEnd( 'total' );
		}

		return this.html;
	};


	/**
	 * Split tokens into chars in the following unresolved regions (gaps):
	 *   - One token became connected or separated by space or dash (or any token)
	 *   - Same number of tokens in gap and strong similarity of all tokens:
	 *     - Addition or deletion of flanking strings in tokens
	 *     - Addition or deletion of internal string in tokens
	 *     - Same length and at least 50 % identity
	 *     - Same start or end, same text longer than different text
	 * Identical tokens including space separators will be linked,
	 *   resulting in word-wise char-level diffs
	 *
	 * @param[in/out] WikEdDiffText newText, oldText Text object tokens list
	 */
	this.splitRefineChars = function () {

		/** Find corresponding gaps. */

		// Cycle through new text tokens list
		var gaps = [];
		var gap = null;
		var i = this.newText.first;
		var j = this.oldText.first;
		while ( i !== null ) {

			// Get token links
			var newLink = this.newText.tokens[i].link;
			var oldLink = null;
			if ( j !== null ) {
				oldLink = this.oldText.tokens[j].link;
			}

			// Start of gap in new and old
			if ( gap === null && newLink === null && oldLink === null ) {
				gap = gaps.length;
				gaps.push( {
					newFirst:  i,
					newLast:   i,
					newTokens: 1,
					oldFirst:  j,
					oldLast:   j,
					oldTokens: null,
					charSplit: null
				} );
			}

			// Count chars and tokens in gap
			else if ( gap !== null && newLink === null ) {
				gaps[gap].newLast = i;
				gaps[gap].newTokens ++;
			}

			// Gap ended
			else if ( gap !== null && newLink !== null ) {
				gap = null;
			}

			// Next list elements
			if ( newLink !== null ) {
				j = this.oldText.tokens[newLink].next;
			}
			i = this.newText.tokens[i].next;
		}

		// Cycle through gaps and add old text gap data
		var gapsLength = gaps.length;
		for ( var gap = 0; gap < gapsLength; gap ++ ) {

			// Cycle through old text tokens list
			var j = gaps[gap].oldFirst;
			while (
				j !== null &&
				this.oldText.tokens[j] !== null &&
				this.oldText.tokens[j].link === null
			) {

				// Count old chars and tokens in gap
				gaps[gap].oldLast = j;
				gaps[gap].oldTokens ++;

				j = this.oldText.tokens[j].next;
			}
		}

		/** Select gaps of identical token number and strong similarity of all tokens. */

		var gapsLength = gaps.length;
		for ( var gap = 0; gap < gapsLength; gap ++ ) {
			var charSplit = true;

			// Not same gap length
			if ( gaps[gap].newTokens !== gaps[gap].oldTokens ) {

				// One word became separated by space, dash, or any string
				if ( gaps[gap].newTokens === 1 && gaps[gap].oldTokens === 3 ) {
					var token = this.newText.tokens[ gaps[gap].newFirst ].token;
					var tokenFirst = this.oldText.tokens[ gaps[gap].oldFirst ].token;
					var tokenLast = this.oldText.tokens[ gaps[gap].oldLast ].token;
					if (
						token.indexOf( tokenFirst ) !== 0 ||
						token.indexOf( tokenLast ) !== token.length - tokenLast.length
					) {
						continue;
					}
				}
				else if ( gaps[gap].oldTokens === 1 && gaps[gap].newTokens === 3 ) {
					var token = this.oldText.tokens[ gaps[gap].oldFirst ].token;
					var tokenFirst = this.newText.tokens[ gaps[gap].newFirst ].token;
					var tokenLast = this.newText.tokens[ gaps[gap].newLast ].token;
					if (
						token.indexOf( tokenFirst ) !== 0 ||
						token.indexOf( tokenLast ) !== token.length - tokenLast.length
					) {
						continue;
					}
				}
				else {
					continue;
				}
				gaps[gap].charSplit = true;
			}

			// Cycle through new text tokens list and set charSplit
			else {
				var i = gaps[gap].newFirst;
				var j = gaps[gap].oldFirst;
				while ( i !== null ) {
					var newToken = this.newText.tokens[i].token;
					var oldToken = this.oldText.tokens[j].token;

					// Get shorter and longer token
					var shorterToken;
					var longerToken;
					if ( newToken.length < oldToken.length ) {
						shorterToken = newToken;
						longerToken = oldToken;
					}
					else {
						shorterToken = oldToken;
						longerToken = newToken;
					}

					// Not same token length
					if ( newToken.length !== oldToken.length ) {

						// Test for addition or deletion of internal string in tokens

						// Find number of identical chars from left
						var left = 0;
						while ( left < shorterToken.length ) {
							if ( newToken.charAt( left ) !== oldToken.charAt( left ) ) {
								break;
							}
							left ++;
						}

						// Find number of identical chars from right
						var right = 0;
						while ( right < shorterToken.length ) {
							if (
								newToken.charAt( newToken.length - 1 - right ) !==
								oldToken.charAt( oldToken.length - 1 - right )
							) {
								break;
							}
							right ++;
						}

						// No simple insertion or deletion of internal string
						if ( left + right !== shorterToken.length ) {

							// Not addition or deletion of flanking strings in tokens
							// Smaller token not part of larger token
							if ( longerToken.indexOf( shorterToken ) === -1 ) {

								// Same text at start or end shorter than different text
								if ( left < shorterToken.length / 2 && (right < shorterToken.length / 2) ) {

									// Do not split into chars in this gap
									charSplit = false;
									break;
								}
							}
						}
					}

					// Same token length
					else if ( newToken !== oldToken ) {

						// Tokens less than 50 % identical
						var ident = 0;
						var tokenLength = shorterToken.length;
						for ( var pos = 0; pos < tokenLength; pos ++ ) {
							if ( shorterToken.charAt( pos ) === longerToken.charAt( pos ) ) {
								ident ++;
							}
						}
						if ( ident / shorterToken.length < 0.49 ) {

							// Do not split into chars this gap
							charSplit = false;
							break;
						}
					}

					// Next list elements
					if ( i === gaps[gap].newLast ) {
						break;
					}
					i = this.newText.tokens[i].next;
					j = this.oldText.tokens[j].next;
				}
				gaps[gap].charSplit = charSplit;
			}
		}

		/** Refine words into chars in selected gaps. */

		var gapsLength = gaps.length;
		for ( var gap = 0; gap < gapsLength; gap ++ ) {
			if ( gaps[gap].charSplit === true ) {

				// Cycle through new text tokens list, link spaces, and split into chars
				var i = gaps[gap].newFirst;
				var j = gaps[gap].oldFirst;
				var newGapLength = i - gaps[gap].newLast;
				var oldGapLength = j - gaps[gap].oldLast;
				while ( i !== null || j !== null ) {

					// Link identical tokens (spaces) to keep char refinement to words
					if (
						newGapLength === oldGapLength &&
						this.newText.tokens[i].token === this.oldText.tokens[j].token
					) {
						this.newText.tokens[i].link = j;
						this.oldText.tokens[j].link = i;
					}

					// Refine words into chars
					else {
						if ( i !== null ) {
							this.newText.splitText( 'character', i );
						}
						if ( j !== null ) {
							this.oldText.splitText( 'character', j );
						}
					}

					// Next list elements
					if ( i === gaps[gap].newLast ) {
						i = null;
					}
					if ( j === gaps[gap].oldLast ) {
						j = null;
					}
					if ( i !== null ) {
						i = this.newText.tokens[i].next;
					}
					if ( j !== null ) {
						j = this.oldText.tokens[j].next;
					}
				}
			}
		}
		return;
	};


	/**
	 * Move gaps with ambiguous identical fronts to last newline border or otherwise last word border.
	 *
	 * @param[in/out] wikEdDiffText text, textLinked These two are newText and oldText
	 */
	this.slideGaps = function ( text, textLinked ) {

		var regExpSlideBorder = this.config.regExp.slideBorder;
		var regExpSlideStop = this.config.regExp.slideStop;

		// Cycle through tokens list
		var i = text.first;
		var gapStart = null;
		while ( i !== null ) {

			// Remember gap start
			if ( gapStart === null && text.tokens[i].link === null ) {
				gapStart = i;
			}

			// Find gap end
			else if ( gapStart !== null && text.tokens[i].link !== null ) {
				var gapFront = gapStart;
				var gapBack = text.tokens[i].prev;

				// Slide down as deep as possible
				var front = gapFront;
				var back = text.tokens[gapBack].next;
				if (
					front !== null &&
					back !== null &&
					text.tokens[front].link === null &&
					text.tokens[back].link !== null &&
					text.tokens[front].token === text.tokens[back].token
				) {
					text.tokens[front].link = text.tokens[back].link;
					textLinked.tokens[ text.tokens[front].link ].link = front;
					text.tokens[back].link = null;

					gapFront = text.tokens[gapFront].next;
					gapBack = text.tokens[gapBack].next;

					front = text.tokens[front].next;
					back = text.tokens[back].next;
				}

				// Test slide up, remember last line break or word border
				var front = text.tokens[gapFront].prev;
				var back = gapBack;
				var gapFrontBlankTest = regExpSlideBorder.test( text.tokens[gapFront].token );
				var frontStop = front;
				if ( text.tokens[back].link === null ) {
					while (
						front !== null &&
						back !== null &&
						text.tokens[front].link !== null &&
						text.tokens[front].token === text.tokens[back].token
					) {
						if ( front !== null ) {

							// Stop at line break
							if ( regExpSlideStop.test( text.tokens[front].token ) === true ) {
								frontStop = front;
								break;
							}

							// Stop at first word border (blank/word or word/blank)
							if (
								regExpSlideBorder.test( text.tokens[front].token ) !== gapFrontBlankTest ) {
								frontStop = front;
							}
						}
						front = text.tokens[front].prev;
						back = text.tokens[back].prev;
					}
				}

				// Actually slide up to stop
				var front = text.tokens[gapFront].prev;
				var back = gapBack;
				while (
					front !== null &&
					back !== null &&
					front !== frontStop &&
					text.tokens[front].link !== null &&
					text.tokens[back].link === null &&
					text.tokens[front].token === text.tokens[back].token
				) {
					text.tokens[back].link = text.tokens[front].link;
					textLinked.tokens[ text.tokens[back].link ].link = back;
					text.tokens[front].link = null;

					front = text.tokens[front].prev;
					back = text.tokens[back].prev;
				}
				gapStart = null;
			}
			i = text.tokens[i].next;
		}
		return;
	};


	/**
	 * Calculate diff information, can be called repeatedly during refining.
	 * Links corresponding tokens from old and new text.
	 * Steps:
	 *   Pass 1: parse new text into symbol table
	 *   Pass 2: parse old text into symbol table
	 *   Pass 3: connect unique matching tokens
	 *   Pass 4: connect adjacent identical tokens downwards
	 *   Pass 5: connect adjacent identical tokens upwards
	 *   Repeat with empty symbol table (against crossed-over gaps)
	 *   Recursively diff still unresolved regions downwards with empty symbol table
	 *   Recursively diff still unresolved regions upwards with empty symbol table
	 *
	 * @param array symbols Symbol table object
	 * @param string level Split level: 'paragraph', 'line', 'sentence', 'chunk', 'word', 'character'
	 *
	 * Optionally for recursive or repeated calls:
	 * @param bool repeating Currently repeating with empty symbol table
	 * @param bool recurse Enable recursion
	 * @param int newStart, newEnd, oldStart, oldEnd Text object tokens indices
	 * @param int recursionLevel Recursion level
	 * @param[in/out] WikEdDiffText newText, oldText Text object, tokens list link property
	 */
	this.calculateDiff = function (
		level,
		recurse,
		repeating,
		newStart,
		oldStart,
		up,
		recursionLevel
	) {

		// Set defaults
		if ( repeating === undefined ) { repeating = false; }
		if ( recurse === undefined ) { recurse = false; }
		if ( newStart === undefined ) { newStart = this.newText.first; }
		if ( oldStart === undefined ) { oldStart = this.oldText.first; }
		if ( up === undefined ) { up = false; }
		if ( recursionLevel === undefined ) { recursionLevel = 0; }

		// Start timers
		if ( this.config.timer === true && repeating === false && recursionLevel === 0 ) {
			this.time( level );
		}
		if ( this.config.timer === true && repeating === false ) {
			this.time( level + recursionLevel );
		}

		// Get object symbols table and linked region borders
		var symbols;
		var bordersDown;
		var bordersUp;
		if ( recursionLevel === 0 && repeating === false ) {
			symbols = this.symbols;
			bordersDown = this.bordersDown;
			bordersUp = this.bordersUp;
		}

		// Create empty local symbols table and linked region borders arrays
		else {
			symbols = {
				token: [],
				hashTable: {},
				linked: false
			};
			bordersDown = [];
			bordersUp = [];
		}


		// Updated versions of linked region borders
		var bordersUpNext = [];
		var bordersDownNext = [];

		/**
		 * Pass 1: parse new text into symbol table.
		 */

		// Cycle through new text tokens list
		var i = newStart;
		while ( i !== null ) {
			if ( this.newText.tokens[i].link === null ) {

				// Add new entry to symbol table
				var token = this.newText.tokens[i].token;
				if ( Object.prototype.hasOwnProperty.call( symbols.hashTable, token ) === false ) {
					symbols.hashTable[token] = symbols.token.length;
					symbols.token.push( {
						newCount: 1,
						oldCount: 0,
						newToken: i,
						oldToken: null
					} );
				}

				// Or update existing entry
				else {

					// Increment token counter for new text
					var hashToArray = symbols.hashTable[token];
					symbols.token[hashToArray].newCount ++;
				}
			}

			// Stop after gap if recursing
			else if ( recursionLevel > 0 ) {
				break;
			}

			// Get next token
			if ( up === false ) {
				i = this.newText.tokens[i].next;
			}
			else {
				i = this.newText.tokens[i].prev;
			}
		}

		/**
		 * Pass 2: parse old text into symbol table.
		 */

		// Cycle through old text tokens list
		var j = oldStart;
		while ( j !== null ) {
			if ( this.oldText.tokens[j].link === null ) {

				// Add new entry to symbol table
				var token = this.oldText.tokens[j].token;
				if ( Object.prototype.hasOwnProperty.call( symbols.hashTable, token ) === false ) {
					symbols.hashTable[token] = symbols.token.length;
					symbols.token.push( {
						newCount: 0,
						oldCount: 1,
						newToken: null,
						oldToken: j
					} );
				}

				// Or update existing entry
				else {

					// Increment token counter for old text
					var hashToArray = symbols.hashTable[token];
					symbols.token[hashToArray].oldCount ++;

					// Add token number for old text
					symbols.token[hashToArray].oldToken = j;
				}
			}

			// Stop after gap if recursing
			else if ( recursionLevel > 0 ) {
				break;
			}

			// Get next token
			if ( up === false ) {
				j = this.oldText.tokens[j].next;
			}
			else {
				j = this.oldText.tokens[j].prev;
			}
		}

		/**
		 * Pass 3: connect unique tokens.
		 */

		// Cycle through symbol array
		var symbolsLength = symbols.token.length;
		for ( var i = 0; i < symbolsLength; i ++ ) {

			// Find tokens in the symbol table that occur only once in both versions
			if ( symbols.token[i].newCount === 1 && symbols.token[i].oldCount === 1 ) {
				var newToken = symbols.token[i].newToken;
				var oldToken = symbols.token[i].oldToken;
				var newTokenObj = this.newText.tokens[newToken];
				var oldTokenObj = this.oldText.tokens[oldToken];

				// Connect from new to old and from old to new
				if ( newTokenObj.link === null ) {

					// Do not use spaces as unique markers
					if (
						this.config.regExp.blankOnlyToken.test( newTokenObj.token ) === true
					) {

						// Link new and old tokens
						newTokenObj.link = oldToken;
						oldTokenObj.link = newToken;
						symbols.linked = true;

						// Save linked region borders
						bordersDown.push( [newToken, oldToken] );
						bordersUp.push( [newToken, oldToken] );

						// Check if token contains unique word
						if ( recursionLevel === 0 ) {
							var unique = false;
							if ( level === 'character' ) {
								unique = true;
							}
							else {
								var token = newTokenObj.token;
								var words =
									( token.match( this.config.regExp.countWords ) || [] ).concat(
										( token.match( this.config.regExp.countChunks ) || [] )
									);

								// Unique if longer than min block length
								var wordsLength = words.length;
								if ( wordsLength >= this.config.blockMinLength ) {
									unique = true;
								}

								// Unique if it contains at least one unique word
								else {
									for ( var i = 0;i < wordsLength; i ++ ) {
										var word = words[i];
										if (
											this.oldText.words[word] === 1 &&
											this.newText.words[word] === 1 &&
											Object.prototype.hasOwnProperty.call( this.oldText.words, word ) === true &&
											Object.prototype.hasOwnProperty.call( this.newText.words, word ) === true
										) {
											unique = true;
											break;
										}
									}
								}
							}

							// Set unique
							if ( unique === true ) {
								newTokenObj.unique = true;
								oldTokenObj.unique = true;
							}
						}
					}
				}
			}
		}

		// Continue passes only if unique tokens have been linked previously
		if ( symbols.linked === true ) {

			/**
			 * Pass 4: connect adjacent identical tokens downwards.
			 */

			// Cycle through list of linked new text tokens
			var bordersLength = bordersDown.length;
			for ( var match = 0; match < bordersLength; match ++ ) {
				var i = bordersDown[match][0];
				var j = bordersDown[match][1];

				// Next down
				var iMatch = i;
				var jMatch = j;
				i = this.newText.tokens[i].next;
				j = this.oldText.tokens[j].next;

				// Cycle through new text list gap region downwards
				while (
					i !== null &&
					j !== null &&
					this.newText.tokens[i].link === null &&
					this.oldText.tokens[j].link === null
				) {

					// Connect if same token
					if ( this.newText.tokens[i].token === this.oldText.tokens[j].token ) {
						this.newText.tokens[i].link = j;
						this.oldText.tokens[j].link = i;
					}

					// Not a match yet, maybe in next refinement level
					else {
						bordersDownNext.push( [iMatch, jMatch] );
						break;
					}

					// Next token down
					iMatch = i;
					jMatch = j;
					i = this.newText.tokens[i].next;
					j = this.oldText.tokens[j].next;
				}
			}

			/**
			 * Pass 5: connect adjacent identical tokens upwards.
			 */

			// Cycle through list of connected new text tokens
			var bordersLength = bordersUp.length;
			for ( var match = 0; match < bordersLength; match ++ ) {
				var i = bordersUp[match][0];
				var j = bordersUp[match][1];

				// Next up
				var iMatch = i;
				var jMatch = j;
				i = this.newText.tokens[i].prev;
				j = this.oldText.tokens[j].prev;

				// Cycle through new text gap region upwards
				while (
					i !== null &&
					j !== null &&
					this.newText.tokens[i].link === null &&
					this.oldText.tokens[j].link === null
				) {

					// Connect if same token
					if ( this.newText.tokens[i].token === this.oldText.tokens[j].token ) {
						this.newText.tokens[i].link = j;
						this.oldText.tokens[j].link = i;
					}

					// Not a match yet, maybe in next refinement level
					else {
						bordersUpNext.push( [iMatch, jMatch] );
						break;
					}

					// Next token up
					iMatch = i;
					jMatch = j;
					i = this.newText.tokens[i].prev;
					j = this.oldText.tokens[j].prev;
				}
			}

			/**
			 * Connect adjacent identical tokens downwards from text start.
			 * Treat boundary as connected, stop after first connected token.
			 */

			// Only for full text diff
			if ( recursionLevel === 0 && repeating === false ) {

				// From start
				var i = this.newText.first;
				var j = this.oldText.first;
				var iMatch = null;
				var jMatch = null;

				// Cycle through old text tokens down
				// Connect identical tokens, stop after first connected token
				while (
					i !== null &&
					j !== null &&
					this.newText.tokens[i].link === null &&
					this.oldText.tokens[j].link === null &&
					this.newText.tokens[i].token === this.oldText.tokens[j].token
				) {
					this.newText.tokens[i].link = j;
					this.oldText.tokens[j].link = i;
					iMatch = i;
					jMatch = j;
					i = this.newText.tokens[i].next;
					j = this.oldText.tokens[j].next;
				}
				if ( iMatch !== null ) {
					bordersDownNext.push( [iMatch, jMatch] );
				}

				// From end
				i = this.newText.last;
				j = this.oldText.last;
				iMatch = null;
				jMatch = null;

				// Cycle through old text tokens up
				// Connect identical tokens, stop after first connected token
				while (
					i !== null &&
					j !== null &&
					this.newText.tokens[i].link === null &&
					this.oldText.tokens[j].link === null &&
					this.newText.tokens[i].token === this.oldText.tokens[j].token
				) {
					this.newText.tokens[i].link = j;
					this.oldText.tokens[j].link = i;
					iMatch = i;
					jMatch = j;
					i = this.newText.tokens[i].prev;
					j = this.oldText.tokens[j].prev;
				}
				if ( iMatch !== null ) {
					bordersUpNext.push( [iMatch, jMatch] );
				}
			}

			// Save updated linked region borders to object
			if ( recursionLevel === 0 && repeating === false ) {
				this.bordersDown = bordersDownNext;
				this.bordersUp = bordersUpNext;
			}

			// Merge local updated linked region borders into object
			else {
				this.bordersDown = this.bordersDown.concat( bordersDownNext );
				this.bordersUp = this.bordersUp.concat( bordersUpNext );
			}


			/**
			 * Repeat once with empty symbol table to link hidden unresolved common tokens in cross-overs.
			 * ("and" in "and this a and b that" -> "and this a and b that")
			 */

			if ( repeating === false && this.config.repeatedDiff === true ) {
				var repeat = true;
				this.calculateDiff( level, recurse, repeat, newStart, oldStart, up, recursionLevel );
			}

			/**
			 * Refine by recursively diffing not linked regions with new symbol table.
			 * At word and character level only.
			 * Helps against gaps caused by addition of common tokens around sequences of common tokens.
			 */

			if (
				recurse === true &&
				this.config['recursiveDiff'] === true &&
				recursionLevel < this.config.recursionMax
			) {

				/**
				 * Recursively diff gap downwards.
				 */

				// Cycle through list of linked region borders
				var bordersLength = bordersDownNext.length;
				for ( match = 0; match < bordersLength; match ++ ) {
					var i = bordersDownNext[match][0];
					var j = bordersDownNext[match][1];

					// Next token down
					i = this.newText.tokens[i].next;
					j = this.oldText.tokens[j].next;

					// Start recursion at first gap token pair
					if (
						i !== null &&
						j !== null &&
						this.newText.tokens[i].link === null &&
						this.oldText.tokens[j].link === null
					) {
						var repeat = false;
						var dirUp = false;
						this.calculateDiff( level, recurse, repeat, i, j, dirUp, recursionLevel + 1 );
					}
				}

				/**
				 * Recursively diff gap upwards.
				 */

				// Cycle through list of linked region borders
				var bordersLength = bordersUpNext.length;
				for ( match = 0; match < bordersLength; match ++ ) {
					var i = bordersUpNext[match][0];
					var j = bordersUpNext[match][1];

					// Next token up
					i = this.newText.tokens[i].prev;
					j = this.oldText.tokens[j].prev;

					// Start recursion at first gap token pair
					if (
						i !== null &&
						j !== null &&
						this.newText.tokens[i].link === null &&
						this.oldText.tokens[j].link === null
					) {
						var repeat = false;
						var dirUp = true;
						this.calculateDiff( level, recurse, repeat, i, j, dirUp, recursionLevel + 1 );
					}
				}
			}
		}

		// Stop timers
		if ( this.config.timer === true && repeating === false ) {
			if ( this.recursionTimer[recursionLevel] === undefined ) {
				this.recursionTimer[recursionLevel] = 0;
			}
			this.recursionTimer[recursionLevel] += this.timeEnd( level + recursionLevel, true );
		}
		if ( this.config.timer === true && repeating === false && recursionLevel === 0 ) {
			this.timeRecursionEnd( level );
			this.timeEnd( level );
		}

		return;
	};


	/**
	 * Main method for processing raw diff data, extracting deleted, inserted, and moved blocks.
	 *
	 * Scheme of blocks, sections, and groups (old block numbers):
	 *   Old:      1    2 3D4   5E6    7   8 9 10  11
	 *             |    ‾/-/_    X     |    >|<     |
	 *   New:      1  I 3D4 2  E6 5  N 7  10 9  8  11
	 *   Section:       0 0 0   1 1       2 2  2
	 *   Group:    0 10 111 2  33 4 11 5   6 7  8   9
	 *   Fixed:    .    +++ -  ++ -    .   . -  -   +
	 *   Type:     =  . =-= =  -= =  . =   = =  =   =
	 *
	 * @param[out] array groups Groups table object
	 * @param[out] array blocks Blocks table object
	 * @param[in/out] WikEdDiffText newText, oldText Text object tokens list
	 */
	this.detectBlocks = function () {

		// Debug log
		if ( this.config.debug === true ) {
			this.oldText.debugText( 'Old text' );
			this.newText.debugText( 'New text' );
		}

		// Collect identical corresponding ('=') blocks from old text and sort by new text
		this.getSameBlocks();

		// Collect independent block sections with no block move crosses outside a section
		this.getSections();

		// Find groups of continuous old text blocks
		this.getGroups();

		// Set longest sequence of increasing groups in sections as fixed (not moved)
		this.setFixed();

		// Convert groups to insertions/deletions if maximum block length is too short
		// Only for more complex texts that actually have blocks of minimum block length
		var unlinkCount = 0;
		if (
			this.config.unlinkBlocks === true &&
			this.config.blockMinLength > 0 &&
			this.maxWords >= this.config.blockMinLength
		) {
			if ( this.config.timer === true ) {
				this.time( 'total unlinking' );
			}

			// Repeat as long as unlinking is possible
			var unlinked = true;
			while ( unlinked === true && unlinkCount < this.config.unlinkMax ) {

				// Convert '=' to '+'/'-' pairs
				unlinked = this.unlinkBlocks();

				// Start over after conversion
				if ( unlinked === true ) {
					unlinkCount ++;
					this.slideGaps( this.newText, this.oldText );
					this.slideGaps( this.oldText, this.newText );

					// Repeat block detection from start
					this.maxWords = 0;
					this.getSameBlocks();
					this.getSections();
					this.getGroups();
					this.setFixed();
				}
			}
			if ( this.config.timer === true ) {
				this.timeEnd( 'total unlinking' );
			}
		}

		// Collect deletion ('-') blocks from old text
		this.getDelBlocks();

		// Position '-' blocks into new text order
		this.positionDelBlocks();

		// Collect insertion ('+') blocks from new text
		this.getInsBlocks();

		// Set group numbers of '+' blocks
		this.setInsGroups();

		// Mark original positions of moved groups
		this.insertMarks();

		// Debug log
		if ( this.config.timer === true || this.config.debug === true ) {
			console.log( 'Unlink count: ', unlinkCount );
		}
		if ( this.config.debug === true ) {
			this.debugGroups( 'Groups' );
			this.debugBlocks( 'Blocks' );
		}
		return;
	};


	/**
	 * Collect identical corresponding matching ('=') blocks from old text and sort by new text.
	 *
	 * @param[in] WikEdDiffText newText, oldText Text objects
	 * @param[in/out] array blocks Blocks table object
	 */
	this.getSameBlocks = function () {

		if ( this.config.timer === true ) {
			this.time( 'getSameBlocks' );
		}

		var blocks = this.blocks;

		// Clear blocks array
		blocks.splice( 0 );

		// Cycle through old text to find connected (linked, matched) blocks
		var j = this.oldText.first;
		var i = null;
		while ( j !== null ) {

			// Skip '-' blocks
			while ( j !== null && this.oldText.tokens[j].link === null ) {
				j = this.oldText.tokens[j].next;
			}

			// Get '=' block
			if ( j !== null ) {
				i = this.oldText.tokens[j].link;
				var iStart = i;
				var jStart = j;

				// Detect matching blocks ('=')
				var count = 0;
				var unique = false;
				var text = '';
				while ( i !== null && j !== null && this.oldText.tokens[j].link === i ) {
					text += this.oldText.tokens[j].token;
					count ++;
					if ( this.newText.tokens[i].unique === true ) {
						unique = true;
					}
					i = this.newText.tokens[i].next;
					j = this.oldText.tokens[j].next;
				}

				// Save old text '=' block
				blocks.push( {
					oldBlock:  blocks.length,
					newBlock:  null,
					oldNumber: this.oldText.tokens[jStart].number,
					newNumber: this.newText.tokens[iStart].number,
					oldStart:  jStart,
					count:     count,
					unique:    unique,
					words:     this.wordCount( text ),
					chars:     text.length,
					type:      '=',
					section:   null,
					group:     null,
					fixed:     null,
					moved:     null,
					text:      text
				} );
			}
		}

		// Sort blocks by new text token number
		blocks.sort( function( a, b ) {
			return a.newNumber - b.newNumber;
		} );

		// Number blocks in new text order
		var blocksLength = blocks.length;
		for ( var block = 0; block < blocksLength; block ++ ) {
			blocks[block].newBlock = block;
		}

		if ( this.config.timer === true ) {
			this.timeEnd( 'getSameBlocks' );
		}
		return;
	};


	/**
	 * Collect independent block sections with no block move crosses
	 * outside a section for per-section determination of non-moving fixed groups.
	 *
	 * @param[out] array sections Sections table object
	 * @param[in/out] array blocks Blocks table object, section property
	 */
	this.getSections = function () {

		if ( this.config.timer === true ) {
			this.time( 'getSections' );
		}

		var blocks = this.blocks;
		var sections = this.sections;

		// Clear sections array
		sections.splice( 0 );

		// Cycle through blocks
		var blocksLength = blocks.length;
		for ( var block = 0; block < blocksLength; block ++ ) {

			var sectionStart = block;
			var sectionEnd = block;

			var oldMax = blocks[sectionStart].oldNumber;
			var sectionOldMax = oldMax;

			// Check right
			for ( var j = sectionStart + 1; j < blocksLength; j ++ ) {

				// Check for crossing over to the left
				if ( blocks[j].oldNumber > oldMax ) {
					oldMax = blocks[j].oldNumber;
				}
				else if ( blocks[j].oldNumber < sectionOldMax ) {
					sectionEnd = j;
					sectionOldMax = oldMax;
				}
			}

			// Save crossing sections
			if ( sectionEnd > sectionStart ) {

				// Save section to block
				for ( var i = sectionStart; i <= sectionEnd; i ++ ) {
					blocks[i].section = sections.length;
				}

				// Save section
				sections.push( {
					blockStart:  sectionStart,
					blockEnd:    sectionEnd
				} );
				block = sectionEnd;
			}
		}
		if ( this.config.timer === true ) {
			this.timeEnd( 'getSections' );
		}
		return;
	};


	/**
	 * Find groups of continuous old text blocks.
	 *
	 * @param[out] array groups Groups table object
	 * @param[in/out] array blocks Blocks table object, group property
	 */
	this.getGroups = function () {

		if ( this.config.timer === true ) {
			this.time( 'getGroups' );
		}

		var blocks = this.blocks;
		var groups = this.groups;

		// Clear groups array
		groups.splice( 0 );

		// Cycle through blocks
		var blocksLength = blocks.length;
		for ( var block = 0; block < blocksLength; block ++ ) {
			var groupStart = block;
			var groupEnd = block;
			var oldBlock = blocks[groupStart].oldBlock;

			// Get word and char count of block
			var words = this.wordCount( blocks[block].text );
			var maxWords = words;
			var unique = blocks[block].unique;
			var chars = blocks[block].chars;

			// Check right
			for ( var i = groupEnd + 1; i < blocksLength; i ++ ) {

				// Check for crossing over to the left
				if ( blocks[i].oldBlock !== oldBlock + 1 ) {
					break;
				}
				oldBlock = blocks[i].oldBlock;

				// Get word and char count of block
				if ( blocks[i].words > maxWords ) {
					maxWords = blocks[i].words;
				}
				if ( blocks[i].unique === true ) {
					unique = true;
				}
				words += blocks[i].words;
				chars += blocks[i].chars;
				groupEnd = i;
			}

			// Save crossing group
			if ( groupEnd >= groupStart ) {

				// Set groups outside sections as fixed
				var fixed = false;
				if ( blocks[groupStart].section === null ) {
					fixed = true;
				}

				// Save group to block
				for ( var i = groupStart; i <= groupEnd; i ++ ) {
					blocks[i].group = groups.length;
					blocks[i].fixed = fixed;
				}

				// Save group
				groups.push( {
					oldNumber:  blocks[groupStart].oldNumber,
					blockStart: groupStart,
					blockEnd:   groupEnd,
					unique:     unique,
					maxWords:   maxWords,
					words:      words,
					chars:      chars,
					fixed:      fixed,
					movedFrom:  null,
					color:      null
				} );
				block = groupEnd;

				// Set global word count of longest linked block
				if ( maxWords > this.maxWords ) {
					this.maxWords = maxWords;
				}
			}
		}
		if ( this.config.timer === true ) {
			this.timeEnd( 'getGroups' );
		}
		return;
	};


	/**
	 * Set longest sequence of increasing groups in sections as fixed (not moved).
	 *
	 * @param[in] array sections Sections table object
	 * @param[in/out] array groups Groups table object, fixed property
	 * @param[in/out] array blocks Blocks table object, fixed property
	 */
	this.setFixed = function () {

		if ( this.config.timer === true ) {
			this.time( 'setFixed' );
		}

		var blocks = this.blocks;
		var groups = this.groups;
		var sections = this.sections;

		// Cycle through sections
		var sectionsLength = sections.length;
		for ( var section = 0; section < sectionsLength; section ++ ) {
			var blockStart = sections[section].blockStart;
			var blockEnd = sections[section].blockEnd;

			var groupStart = blocks[blockStart].group;
			var groupEnd = blocks[blockEnd].group;

			// Recusively find path of groups in increasing old group order with longest char length
			var cache = [];
			var maxChars = 0;
			var maxPath = null;

			// Start at each group of section
			for ( var i = groupStart; i <= groupEnd; i ++ ) {
				var pathObj = this.findMaxPath( i, groupEnd, cache );
				if ( pathObj.chars > maxChars ) {
					maxPath = pathObj.path;
					maxChars = pathObj.chars;
				}
			}

			// Mark fixed groups
			var maxPathLength = maxPath.length;
			for ( var i = 0; i < maxPathLength; i ++ ) {
				var group = maxPath[i];
				groups[group].fixed = true;

				// Mark fixed blocks
				for ( var block = groups[group].blockStart; block <= groups[group].blockEnd; block ++ ) {
					blocks[block].fixed = true;
				}
			}
		}
		if ( this.config.timer === true ) {
			this.timeEnd( 'setFixed' );
		}
		return;
	};


	/**
	 * Recusively find path of groups in increasing old group order with longest char length.
	 *
	 * @param int start Path start group
	 * @param int groupEnd Path last group
	 * @param array cache Cache object, contains returnObj for start
	 * @return array returnObj Contains path and char length
	 */
	this.findMaxPath = function ( start, groupEnd, cache ) {

		var groups = this.groups;

		// Find longest sub-path
		var maxChars = 0;
		var oldNumber = groups[start].oldNumber;
		var returnObj = { path: [], chars: 0};
		for ( var i = start + 1; i <= groupEnd; i ++ ) {

			// Only in increasing old group order
			if ( groups[i].oldNumber < oldNumber ) {
				continue;
			}

			// Get longest sub-path from cache (deep copy)
			var pathObj;
			if ( cache[i] !== undefined ) {
				pathObj = { path: cache[i].path.slice(), chars: cache[i].chars };
			}

			// Get longest sub-path by recursion
			else {
				pathObj = this.findMaxPath( i, groupEnd, cache );
			}

			// Select longest sub-path
			if ( pathObj.chars > maxChars ) {
				maxChars = pathObj.chars;
				returnObj = pathObj;
			}
		}

		// Add current start to path
		returnObj.path.unshift( start );
		returnObj.chars += groups[start].chars;

		// Save path to cache (deep copy)
		if ( cache[start] === undefined ) {
			cache[start] = { path: returnObj.path.slice(), chars: returnObj.chars };
		}

		return returnObj;
	};


	/**
	 * Convert matching '=' blocks in groups into insertion/deletion ('+'/'-') pairs
	 * if too short and too common.
	 * Prevents fragmentated diffs for very different versions.
	 *
	 * @param[in] array blocks Blocks table object
	 * @param[in/out] WikEdDiffText newText, oldText Text object, linked property
	 * @param[in/out] array groups Groups table object
	 * @return bool True if text tokens were unlinked
	 */
	this.unlinkBlocks = function () {

		var blocks = this.blocks;
		var groups = this.groups;

		// Cycle through groups
		var unlinked = false;
		var groupsLength = groups.length;
		for ( var group = 0; group < groupsLength; group ++ ) {
			var blockStart = groups[group].blockStart;
			var blockEnd = groups[group].blockEnd;

			// Unlink whole group if no block is at least blockMinLength words long and unique
			if ( groups[group].maxWords < this.config.blockMinLength && groups[group].unique === false ) {
				for ( var block = blockStart; block <= blockEnd; block ++ ) {
					if ( blocks[block].type === '=' ) {
						this.unlinkSingleBlock( blocks[block] );
						unlinked = true;
					}
				}
			}

			// Otherwise unlink block flanks
			else {

				// Unlink blocks from start
				for ( var block = blockStart; block <= blockEnd; block ++ ) {
					if ( blocks[block].type === '=' ) {

						// Stop unlinking if more than one word or a unique word
						if ( blocks[block].words > 1 || blocks[block].unique === true ) {
							break;
						}
						this.unlinkSingleBlock( blocks[block] );
						unlinked = true;
						blockStart = block;
					}
				}

				// Unlink blocks from end
				for ( var block = blockEnd; block > blockStart; block -- ) {
					if ( blocks[block].type === '=' ) {

						// Stop unlinking if more than one word or a unique word
						if (
							blocks[block].words > 1 ||
							( blocks[block].words === 1 && blocks[block].unique === true )
						) {
							break;
						}
						this.unlinkSingleBlock( blocks[block] );
						unlinked = true;
					}
				}
			}
		}
		return unlinked;
	};


	/**
	 * Unlink text tokens of single block, convert them into into insertion/deletion ('+'/'-') pairs.
	 *
	 * @param[in] array blocks Blocks table object
	 * @param[out] WikEdDiffText newText, oldText Text objects, link property
	 */
	this.unlinkSingleBlock = function ( block ) {

		// Cycle through old text
		var j = block.oldStart;
		for ( var count = 0; count < block.count; count ++ ) {

			// Unlink tokens
			this.newText.tokens[ this.oldText.tokens[j].link ].link = null;
			this.oldText.tokens[j].link = null;
			j = this.oldText.tokens[j].next;
		}
		return;
	};


	/**
	 * Collect deletion ('-') blocks from old text.
	 *
	 * @param[in] WikEdDiffText oldText Old Text object
	 * @param[out] array blocks Blocks table object
	 */
	this.getDelBlocks = function () {

		if ( this.config.timer === true ) {
			this.time( 'getDelBlocks' );
		}

		var blocks = this.blocks;

		// Cycle through old text to find connected (linked, matched) blocks
		var j = this.oldText.first;
		var i = null;
		while ( j !== null ) {

			// Collect '-' blocks
			var oldStart = j;
			var count = 0;
			var text = '';
			while ( j !== null && this.oldText.tokens[j].link === null ) {
				count ++;
				text += this.oldText.tokens[j].token;
				j = this.oldText.tokens[j].next;
			}

			// Save old text '-' block
			if ( count !== 0 ) {
				blocks.push( {
					oldBlock:  null,
					newBlock:  null,
					oldNumber: this.oldText.tokens[oldStart].number,
					newNumber: null,
					oldStart:  oldStart,
					count:     count,
					unique:    false,
					words:     null,
					chars:     text.length,
					type:      '-',
					section:   null,
					group:     null,
					fixed:     null,
					moved:     null,
					text:      text
				} );
			}

			// Skip '=' blocks
			if ( j !== null ) {
				i = this.oldText.tokens[j].link;
				while ( i !== null && j !== null && this.oldText.tokens[j].link === i ) {
					i = this.newText.tokens[i].next;
					j = this.oldText.tokens[j].next;
				}
			}
		}
		if ( this.config.timer === true ) {
			this.timeEnd( 'getDelBlocks' );
		}
		return;
	};


	/**
	 * Position deletion '-' blocks into new text order.
	 * Deletion blocks move with fixed reference:
	 *   Old:          1 D 2      1 D 2
	 *                /     \    /   \ \
	 *   New:        1 D     2  1     D 2
	 *   Fixed:      *                  *
	 *   newNumber:  1 1              2 2
	 *
	 * Marks '|' and deletions '-' get newNumber of reference block
	 * and are sorted around it by old text number.
	 *
	 * @param[in/out] array blocks Blocks table, newNumber, section, group, and fixed properties
	 *
	 */
	this.positionDelBlocks = function () {

		if ( this.config.timer === true ) {
			this.time( 'positionDelBlocks' );
		}

		var blocks = this.blocks;
		var groups = this.groups;

		// Sort shallow copy of blocks by oldNumber
		var blocksOld = blocks.slice();
		blocksOld.sort( function( a, b ) {
			return a.oldNumber - b.oldNumber;
		} );

		// Cycle through blocks in old text order
		var blocksOldLength = blocksOld.length;
		for ( var block = 0; block < blocksOldLength; block ++ ) {
			var delBlock = blocksOld[block];

			// '-' block only
			if ( delBlock.type !== '-' ) {
				continue;
			}

			// Find fixed '=' reference block from original block position to position '-' block
			// Similar to position marks '|' code

			// Get old text prev block
			var prevBlockNumber = null;
			var prevBlock = null;
			if ( block > 0 ) {
				prevBlockNumber = blocksOld[block - 1].newBlock;
				prevBlock = blocks[prevBlockNumber];
			}

			// Get old text next block
			var nextBlockNumber = null;
			var nextBlock = null;
			if ( block < blocksOld.length - 1 ) {
				nextBlockNumber = blocksOld[block + 1].newBlock;
				nextBlock = blocks[nextBlockNumber];
			}

			// Move after prev block if fixed
			var refBlock = null;
			if ( prevBlock !== null && prevBlock.type === '=' && prevBlock.fixed === true ) {
				refBlock = prevBlock;
			}

			// Move before next block if fixed
			else if ( nextBlock !== null && nextBlock.type === '=' && nextBlock.fixed === true ) {
				refBlock = nextBlock;
			}

			// Move after prev block if not start of group
			else if (
				prevBlock !== null &&
				prevBlock.type === '=' &&
				prevBlockNumber !== groups[ prevBlock.group ].blockEnd
			) {
				refBlock = prevBlock;
			}

			// Move before next block if not start of group
			else if (
				nextBlock !== null &&
				nextBlock.type === '=' &&
				nextBlockNumber !== groups[ nextBlock.group ].blockStart
			) {
				refBlock = nextBlock;
			}

			// Move after closest previous fixed block
			else {
				for ( var fixed = block; fixed >= 0; fixed -- ) {
					if ( blocksOld[fixed].type === '=' && blocksOld[fixed].fixed === true ) {
						refBlock = blocksOld[fixed];
						break;
					}
				}
			}

			// Move before first block
			if ( refBlock === null ) {
				delBlock.newNumber =  -1;
			}

			// Update '-' block data
			else {
				delBlock.newNumber = refBlock.newNumber;
				delBlock.section = refBlock.section;
				delBlock.group = refBlock.group;
				delBlock.fixed = refBlock.fixed;
			}
		}

		// Sort '-' blocks in and update groups
		this.sortBlocks();

		if ( this.config.timer === true ) {
			this.timeEnd( 'positionDelBlocks' );
		}
		return;
	};


	/**
	 * Collect insertion ('+') blocks from new text.
	 *
	 * @param[in] WikEdDiffText newText New Text object
	 * @param[out] array blocks Blocks table object
	 */
	this.getInsBlocks = function () {

		if ( this.config.timer === true ) {
			this.time( 'getInsBlocks' );
		}

		var blocks = this.blocks;

		// Cycle through new text to find insertion blocks
		var i = this.newText.first;
		while ( i !== null ) {

			// Jump over linked (matched) block
			while ( i !== null && this.newText.tokens[i].link !== null ) {
				i = this.newText.tokens[i].next;
			}

			// Detect insertion blocks ('+')
			if ( i !== null ) {
				var iStart = i;
				var count = 0;
				var text = '';
				while ( i !== null && this.newText.tokens[i].link === null ) {
					count ++;
					text += this.newText.tokens[i].token;
					i = this.newText.tokens[i].next;
				}

				// Save new text '+' block
				blocks.push( {
					oldBlock:  null,
					newBlock:  null,
					oldNumber: null,
					newNumber: this.newText.tokens[iStart].number,
					oldStart:  null,
					count:     count,
					unique:    false,
					words:     null,
					chars:     text.length,
					type:      '+',
					section:   null,
					group:     null,
					fixed:     null,
					moved:     null,
					text:      text
				} );
			}
		}

		// Sort '+' blocks in and update groups
		this.sortBlocks();

		if ( this.config.timer === true ) {
			this.timeEnd( 'getInsBlocks' );
		}
		return;
	};


	/**
	 * Sort blocks by new text token number and update groups.
	 *
	 * @param[in/out] array groups Groups table object
	 * @param[in/out] array blocks Blocks table object
	 */
	this.sortBlocks = function () {

		var blocks = this.blocks;
		var groups = this.groups;

		// Sort by newNumber, then by old number
		blocks.sort( function( a, b ) {
			var comp = a.newNumber - b.newNumber;
			if ( comp === 0 ) {
				comp = a.oldNumber - b.oldNumber;
			}
			return comp;
		} );

		// Cycle through blocks and update groups with new block numbers
		var group = null;
		var blocksLength = blocks.length;
		for ( var block = 0; block < blocksLength; block ++ ) {
			var blockGroup = blocks[block].group;
			if ( blockGroup !== null ) {
				if ( blockGroup !== group ) {
					group = blocks[block].group;
					groups[group].blockStart = block;
					groups[group].oldNumber = blocks[block].oldNumber;
				}
				groups[blockGroup].blockEnd = block;
			}
		}
		return;
	};


	/**
	 * Set group numbers of insertion '+' blocks.
	 *
	 * @param[in/out] array groups Groups table object
	 * @param[in/out] array blocks Blocks table object, fixed and group properties
	 */
	this.setInsGroups = function () {

		if ( this.config.timer === true ) {
			this.time( 'setInsGroups' );
		}

		var blocks = this.blocks;
		var groups = this.groups;

		// Set group numbers of '+' blocks inside existing groups
		var groupsLength = groups.length;
		for ( var group = 0; group < groupsLength; group ++ ) {
			var fixed = groups[group].fixed;
			for ( var block = groups[group].blockStart; block <= groups[group].blockEnd; block ++ ) {
				if ( blocks[block].group === null ) {
					blocks[block].group = group;
					blocks[block].fixed = fixed;
				}
			}
		}

		// Add remaining '+' blocks to new groups

		// Cycle through blocks
		var blocksLength = blocks.length;
		for ( var block = 0; block < blocksLength; block ++ ) {

			// Skip existing groups
			if ( blocks[block].group === null ) {
				blocks[block].group = groups.length;

				// Save new single-block group
				groups.push( {
					oldNumber:  blocks[block].oldNumber,
					blockStart: block,
					blockEnd:   block,
					unique:     blocks[block].unique,
					maxWords:   blocks[block].words,
					words:      blocks[block].words,
					chars:      blocks[block].chars,
					fixed:      blocks[block].fixed,
					movedFrom:  null,
					color:      null
				} );
			}
		}
		if ( this.config.timer === true ) {
			this.timeEnd( 'setInsGroups' );
		}
		return;
	};


	/**
	 * Mark original positions of moved groups.
	 * Scheme: moved block marks at original positions relative to fixed groups:
	 *   Groups:    3       7
	 *           1 <|       |     (no next smaller fixed)
	 *           5  |<      |
	 *              |>  5   |
	 *              |   5  <|
	 *              |      >|   5
	 *              |       |>  9 (no next larger fixed)
	 *   Fixed:     *       *
	 *
	 * Mark direction: groups.movedGroup.blockStart < groups.group.blockStart
	 * Group side:     groups.movedGroup.oldNumber < groups.group.oldNumber
	 *
	 * Marks '|' and deletions '-' get newNumber of reference block
	 * and are sorted around it by old text number.
	 *
	 * @param[in/out] array groups Groups table object, movedFrom property
	 * @param[in/out] array blocks Blocks table object
	 */
	this.insertMarks = function () {

		if ( this.config.timer === true ) {
			this.time( 'insertMarks' );
		}

		var blocks = this.blocks;
		var groups = this.groups;
		var moved = [];
		var color = 1;

		// Make shallow copy of blocks
		var blocksOld = blocks.slice();

		// Enumerate copy
		var blocksOldLength = blocksOld.length;
		for ( var i = 0; i < blocksOldLength; i ++ ) {
			blocksOld[i].number = i;
		}

		// Sort copy by oldNumber
		blocksOld.sort( function( a, b ) {
			var comp = a.oldNumber - b.oldNumber;
			if ( comp === 0 ) {
				comp = a.newNumber - b.newNumber;
			}
			return comp;
		} );

		// Create lookup table: original to sorted
		var lookupSorted = [];
		for ( var i = 0; i < blocksOldLength; i ++ ) {
			lookupSorted[ blocksOld[i].number ] = i;
		}

		// Cycle through groups (moved group)
		var groupsLength = groups.length;
		for ( var moved = 0; moved < groupsLength; moved ++ ) {
			var movedGroup = groups[moved];
			if ( movedGroup.fixed !== false ) {
				continue;
			}
			var movedOldNumber = movedGroup.oldNumber;

			// Find fixed '=' reference block from original block position to position '|' block
			// Similar to position deletions '-' code

			// Get old text prev block
			var prevBlock = null;
			var block = lookupSorted[ movedGroup.blockStart ];
			if ( block > 0 ) {
				prevBlock = blocksOld[block - 1];
			}

			// Get old text next block
			var nextBlock = null;
			var block = lookupSorted[ movedGroup.blockEnd ];
			if ( block < blocksOld.length - 1 ) {
				nextBlock = blocksOld[block + 1];
			}

			// Move after prev block if fixed
			var refBlock = null;
			if ( prevBlock !== null && prevBlock.type === '=' && prevBlock.fixed === true ) {
				refBlock = prevBlock;
			}

			// Move before next block if fixed
			else if ( nextBlock !== null && nextBlock.type === '=' && nextBlock.fixed === true ) {
				refBlock = nextBlock;
			}

			// Find closest fixed block to the left
			else {
				for ( var fixed = lookupSorted[ movedGroup.blockStart ] - 1; fixed >= 0; fixed -- ) {
					if ( blocksOld[fixed].type === '=' && blocksOld[fixed].fixed === true ) {
						refBlock = blocksOld[fixed];
						break;
					}
				}
			}

			// Get position of new mark block
			var newNumber;
			var markGroup;

			// No smaller fixed block, moved right from before first block
			if ( refBlock === null ) {
				newNumber = -1;
				markGroup = groups.length;

				// Save new single-mark-block group
				groups.push( {
					oldNumber:  0,
					blockStart: blocks.length,
					blockEnd:   blocks.length,
					unique:     false,
					maxWords:   null,
					words:      null,
					chars:      0,
					fixed:      null,
					movedFrom:  null,
					color:      null
				} );
			}
			else {
				newNumber = refBlock.newNumber;
				markGroup = refBlock.group;
			}

			// Insert '|' block
			blocks.push( {
				oldBlock:  null,
				newBlock:  null,
				oldNumber: movedOldNumber,
				newNumber: newNumber,
				oldStart:  null,
				count:     null,
				unique:    null,
				words:     null,
				chars:     0,
				type:      '|',
				section:   null,
				group:     markGroup,
				fixed:     true,
				moved:     moved,
				text:      ''
			} );

			// Set group color
			movedGroup.color = color;
			movedGroup.movedFrom = markGroup;
			color ++;
		}

		// Sort '|' blocks in and update groups
		this.sortBlocks();

		if ( this.config.timer === true ) {
			this.timeEnd( 'insertMarks' );
		}
		return;
	};


	/**
	 * Collect diff fragment list for markup, create abstraction layer for customized diffs.
	 * Adds the following fagment types:
	 *   '=', '-', '+'   same, deletion, insertion
	 *   '<', '>'        mark left, mark right
	 *   '(<', '(>', ')' block start and end
	 *   '[', ']'        fragment start and end
	 *   '{', '}'        container start and end
	 *
	 * @param[in] array groups Groups table object
	 * @param[in] array blocks Blocks table object
	 * @param[out] array fragments Fragments array, abstraction layer for diff code
	 */
	this.getDiffFragments = function () {

		var blocks = this.blocks;
		var groups = this.groups;
		var fragments = this.fragments;

		// Make shallow copy of groups and sort by blockStart
		var groupsSort = groups.slice();
		groupsSort.sort( function( a, b ) {
			return a.blockStart - b.blockStart;
		} );

		// Cycle through groups
		var groupsSortLength = groupsSort.length;
		for ( var group = 0; group < groupsSortLength; group ++ ) {
			var blockStart = groupsSort[group].blockStart;
			var blockEnd = groupsSort[group].blockEnd;

			// Add moved block start
			var color = groupsSort[group].color;
			if ( color !== null ) {
				var type;
				if ( groupsSort[group].movedFrom < blocks[ blockStart ].group ) {
					type = '(<';
				}
				else {
					type = '(>';
				}
				fragments.push( {
					text:  '',
					type:  type,
					color: color
				} );
			}

			// Cycle through blocks
			for ( var block = blockStart; block <= blockEnd; block ++ ) {
				var type = blocks[block].type;

				// Add '=' unchanged text and moved block
				if ( type === '=' || type === '-' || type === '+' ) {
					fragments.push( {
						text:  blocks[block].text,
						type:  type,
						color: color
					} );
				}

				// Add '<' and '>' marks
				else if ( type === '|' ) {
					var movedGroup = groups[ blocks[block].moved ];

					// Get mark text
					var markText = '';
					for (
						var movedBlock = movedGroup.blockStart;
						movedBlock <= movedGroup.blockEnd;
						movedBlock ++
					) {
						if ( blocks[movedBlock].type === '=' || blocks[movedBlock].type === '-' ) {
							markText += blocks[movedBlock].text;
						}
					}

					// Get mark direction
					var markType;
					if ( movedGroup.blockStart < blockStart ) {
						markType = '<';
					}
					else {
						markType = '>';
					}

					// Add mark
					fragments.push( {
						text:  markText,
						type:  markType,
						color: movedGroup.color
					} );
				}
			}

			// Add moved block end
			if ( color !== null ) {
				fragments.push( {
					text:  '',
					type:  ' )',
					color: color
				} );
			}
		}

		// Cycle through fragments, join consecutive fragments of same type (i.e. '-' blocks)
		var fragmentsLength = fragments.length;
		for ( var fragment = 1; fragment < fragmentsLength; fragment ++ ) {

			// Check if joinable
			if (
				fragments[fragment].type === fragments[fragment - 1].type &&
				fragments[fragment].color === fragments[fragment - 1].color &&
				fragments[fragment].text !== '' && fragments[fragment - 1].text !== ''
			) {

				// Join and splice
				fragments[fragment - 1].text += fragments[fragment].text;
				fragments.splice( fragment, 1 );
				fragment --;
			}
		}

		// Enclose in containers
		fragments.unshift( { text: '', type: '{', color: null }, { text: '', type: '[', color: null } );
		fragments.push(    { text: '', type: ']', color: null }, { text: '', type: '}', color: null } );

		return;
	};


	/**
	 * Clip unchanged sections from unmoved block text.
	 * Adds the following fagment types:
	 *   '~', ' ~', '~ ' omission indicators
	 *   '[', ']', ','   fragment start and end, fragment separator
	 *
	 * @param[in/out] array fragments Fragments array, abstraction layer for diff code
	 */
	this.clipDiffFragments = function () {

		var fragments = this.fragments;

		// Skip if only one fragment in containers, no change
		if ( fragments.length === 5 ) {
			return;
		}

		// Min length for clipping right
		var minRight = this.config.clipHeadingRight;
		if ( this.config.clipParagraphRightMin < minRight ) {
			minRight = this.config.clipParagraphRightMin;
		}
		if ( this.config.clipLineRightMin < minRight ) {
			minRight = this.config.clipLineRightMin;
		}
		if ( this.config.clipBlankRightMin < minRight ) {
			minRight = this.config.clipBlankRightMin;
		}
		if ( this.config.clipCharsRight < minRight ) {
			minRight = this.config.clipCharsRight;
		}

		// Min length for clipping left
		var minLeft = this.config.clipHeadingLeft;
		if ( this.config.clipParagraphLeftMin < minLeft ) {
			minLeft = this.config.clipParagraphLeftMin;
		}
		if ( this.config.clipLineLeftMin < minLeft ) {
			minLeft = this.config.clipLineLeftMin;
		}
		if ( this.config.clipBlankLeftMin < minLeft ) {
			minLeft = this.config.clipBlankLeftMin;
		}
		if ( this.config.clipCharsLeft < minLeft ) {
			minLeft = this.config.clipCharsLeft;
		}

		// Cycle through fragments
		var fragmentsLength = fragments.length;
		for ( var fragment = 0; fragment < fragmentsLength; fragment ++ ) {

			// Skip if not an unmoved and unchanged block
			var type = fragments[fragment].type;
			var color = fragments[fragment].color;
			if ( type !== '=' || color !== null ) {
				continue;
			}

			// Skip if too short for clipping
			var text = fragments[fragment].text;
			var textLength = text.length;
			if ( textLength < minRight && textLength < minLeft ) {
				continue;
			}

			// Get line positions including start and end
			var lines = [];
			var lastIndex = null;
			var regExpMatch;
			while ( ( regExpMatch = this.config.regExp.clipLine.exec( text ) ) !== null ) {
				lines.push( regExpMatch.index );
				lastIndex = this.config.regExp.clipLine.lastIndex;
			}
			if ( lines[0] !== 0 ) {
				lines.unshift( 0 );
			}
			if ( lastIndex !== textLength ) {
				lines.push( textLength );
			}

			// Get heading positions
			var headings = [];
			var headingsEnd = [];
			while ( ( regExpMatch = this.config.regExp.clipHeading.exec( text ) ) !== null ) {
				headings.push( regExpMatch.index );
				headingsEnd.push( regExpMatch.index + regExpMatch[0].length );
			}

			// Get paragraph positions including start and end
			var paragraphs = [];
			var lastIndex = null;
			while ( ( regExpMatch = this.config.regExp.clipParagraph.exec( text ) ) !== null ) {
				paragraphs.push( regExpMatch.index );
				lastIndex = this.config.regExp.clipParagraph.lastIndex;
			}
			if ( paragraphs[0] !== 0 ) {
				paragraphs.unshift( 0 );
			}
			if ( lastIndex !== textLength ) {
				paragraphs.push( textLength );
			}

			// Determine ranges to keep on left and right side
			var rangeRight = null;
			var rangeLeft = null;
			var rangeRightType = '';
			var rangeLeftType = '';

			// Find clip pos from left, skip for first non-container block
			if ( fragment !== 2 ) {

				// Maximum lines to search from left
				var rangeLeftMax = textLength;
				if ( this.config.clipLinesLeftMax < lines.length ) {
					rangeLeftMax = lines[this.config.clipLinesLeftMax];
				}

				// Find first heading from left
				if ( rangeLeft === null ) {
					var headingsLength = headingsEnd.length;
					for ( var j = 0; j < headingsLength; j ++ ) {
						if ( headingsEnd[j] > this.config.clipHeadingLeft || headingsEnd[j] > rangeLeftMax ) {
							break;
						}
						rangeLeft = headingsEnd[j];
						rangeLeftType = 'heading';
						break;
					}
				}

				// Find first paragraph from left
				if ( rangeLeft === null ) {
					var paragraphsLength = paragraphs.length;
					for ( var j = 0; j < paragraphsLength; j ++ ) {
						if (
							paragraphs[j] > this.config.clipParagraphLeftMax ||
							paragraphs[j] > rangeLeftMax
						) {
							break;
						}
						if ( paragraphs[j] > this.config.clipParagraphLeftMin ) {
							rangeLeft = paragraphs[j];
							rangeLeftType = 'paragraph';
							break;
						}
					}
				}

				// Find first line break from left
				if ( rangeLeft === null ) {
					var linesLength = lines.length;
					for ( var j = 0; j < linesLength; j ++ ) {
						if ( lines[j] > this.config.clipLineLeftMax || lines[j] > rangeLeftMax ) {
							break;
						}
						if ( lines[j] > this.config.clipLineLeftMin ) {
							rangeLeft = lines[j];
							rangeLeftType = 'line';
							break;
						}
					}
				}

				// Find first blank from left
				if ( rangeLeft === null ) {
					this.config.regExp.clipBlank.lastIndex = this.config.clipBlankLeftMin;
					if ( ( regExpMatch = this.config.regExp.clipBlank.exec( text ) ) !== null ) {
						if (
							regExpMatch.index < this.config.clipBlankLeftMax &&
							regExpMatch.index < rangeLeftMax
						) {
							rangeLeft = regExpMatch.index;
							rangeLeftType = 'blank';
						}
					}
				}

				// Fixed number of chars from left
				if ( rangeLeft === null ) {
					if ( this.config.clipCharsLeft < rangeLeftMax ) {
						rangeLeft = this.config.clipCharsLeft;
						rangeLeftType = 'chars';
					}
				}

				// Fixed number of lines from left
				if ( rangeLeft === null ) {
					rangeLeft = rangeLeftMax;
					rangeLeftType = 'fixed';
				}
			}

			// Find clip pos from right, skip for last non-container block
			if ( fragment !== fragments.length - 3 ) {

				// Maximum lines to search from right
				var rangeRightMin = 0;
				if ( lines.length >= this.config.clipLinesRightMax ) {
					rangeRightMin = lines[lines.length - this.config.clipLinesRightMax];
				}

				// Find last heading from right
				if ( rangeRight === null ) {
					for ( var j = headings.length - 1; j >= 0; j -- ) {
						if (
							headings[j] < textLength - this.config.clipHeadingRight ||
							headings[j] < rangeRightMin
						) {
							break;
						}
						rangeRight = headings[j];
						rangeRightType = 'heading';
						break;
					}
				}

				// Find last paragraph from right
				if ( rangeRight === null ) {
					for ( var j = paragraphs.length - 1; j >= 0 ; j -- ) {
						if (
							paragraphs[j] < textLength - this.config.clipParagraphRightMax ||
							paragraphs[j] < rangeRightMin
						) {
							break;
						}
						if ( paragraphs[j] < textLength - this.config.clipParagraphRightMin ) {
							rangeRight = paragraphs[j];
							rangeRightType = 'paragraph';
							break;
						}
					}
				}

				// Find last line break from right
				if ( rangeRight === null ) {
					for ( var j = lines.length - 1; j >= 0; j -- ) {
						if (
							lines[j] < textLength - this.config.clipLineRightMax ||
							lines[j] < rangeRightMin
						) {
							break;
						}
						if ( lines[j] < textLength - this.config.clipLineRightMin ) {
							rangeRight = lines[j];
							rangeRightType = 'line';
							break;
						}
					}
				}

				// Find last blank from right
				if ( rangeRight === null ) {
					var startPos = textLength - this.config.clipBlankRightMax;
					if ( startPos < rangeRightMin ) {
						startPos = rangeRightMin;
					}
					this.config.regExp.clipBlank.lastIndex = startPos;
					var lastPos = null;
					while ( ( regExpMatch = this.config.regExp.clipBlank.exec( text ) ) !== null ) {
						if ( regExpMatch.index > textLength - this.config.clipBlankRightMin ) {
							if ( lastPos !== null ) {
								rangeRight = lastPos;
								rangeRightType = 'blank';
							}
							break;
						}
						lastPos = regExpMatch.index;
					}
				}

				// Fixed number of chars from right
				if ( rangeRight === null ) {
					if ( textLength - this.config.clipCharsRight > rangeRightMin ) {
						rangeRight = textLength - this.config.clipCharsRight;
						rangeRightType = 'chars';
					}
				}

				// Fixed number of lines from right
				if ( rangeRight === null ) {
					rangeRight = rangeRightMin;
					rangeRightType = 'fixed';
				}
			}

			// Check if we skip clipping if ranges are close together
			if ( rangeLeft !== null && rangeRight !== null ) {

				// Skip if overlapping ranges
				if ( rangeLeft > rangeRight ) {
					continue;
				}

				// Skip if chars too close
				var skipChars = rangeRight - rangeLeft;
				if ( skipChars < this.config.clipSkipChars ) {
					continue;
				}

				// Skip if lines too close
				var skipLines = 0;
				var linesLength = lines.length;
				for ( var j = 0; j < linesLength; j ++ ) {
					if ( lines[j] > rangeRight || skipLines > this.config.clipSkipLines ) {
						break;
					}
					if ( lines[j] > rangeLeft ) {
						skipLines ++;
					}
				}
				if ( skipLines < this.config.clipSkipLines ) {
					continue;
				}
			}

			// Skip if nothing to clip
			if ( rangeLeft === null && rangeRight === null ) {
				continue;
			}

			// Split left text
			var textLeft = null;
			var omittedLeft = null;
			if ( rangeLeft !== null ) {
				textLeft = text.slice( 0, rangeLeft );

				// Remove trailing empty lines
				textLeft = textLeft.replace( this.config.regExp.clipTrimNewLinesLeft, '' );

				// Get omission indicators, remove trailing blanks
				if ( rangeLeftType === 'chars' ) {
					omittedLeft = '~';
					textLeft = textLeft.replace( this.config.regExp.clipTrimBlanksLeft, '' );
				}
				else if ( rangeLeftType === 'blank' ) {
					omittedLeft = ' ~';
					textLeft = textLeft.replace( this.config.regExp.clipTrimBlanksLeft, '' );
				}
			}

			// Split right text
			var textRight = null;
			var omittedRight = null;
			if ( rangeRight !== null ) {
				textRight = text.slice( rangeRight );

				// Remove leading empty lines
				textRight = textRight.replace( this.config.regExp.clipTrimNewLinesRight, '' );

				// Get omission indicators, remove leading blanks
				if ( rangeRightType === 'chars' ) {
					omittedRight = '~';
					textRight = textRight.replace( this.config.regExp.clipTrimBlanksRight, '' );
				}
				else if ( rangeRightType === 'blank' ) {
					omittedRight = '~ ';
					textRight = textRight.replace( this.config.regExp.clipTrimBlanksRight, '' );
				}
			}

			// Remove split element
			fragments.splice( fragment, 1 );
			fragmentsLength --;

			// Add left text to fragments list
			if ( rangeLeft !== null ) {
				fragments.splice( fragment ++, 0, { text: textLeft, type: '=', color: null } );
				fragmentsLength ++;
				if ( omittedLeft !== null ) {
					fragments.splice( fragment ++, 0,	{ text: '', type: omittedLeft, color: null } );
					fragmentsLength ++;
				}
			}

			// Add fragment container and separator to list
			if ( rangeLeft !== null && rangeRight !== null ) {
				fragments.splice( fragment ++, 0, { text: '', type: ']', color: null } );
				fragments.splice( fragment ++, 0, { text: '', type: ',', color: null } );
				fragments.splice( fragment ++, 0, { text: '', type: '[', color: null } );
				fragmentsLength += 3;
			}

			// Add right text to fragments list
			if ( rangeRight !== null ) {
				if ( omittedRight !== null ) {
					fragments.splice( fragment ++, 0, { text: '', type: omittedRight, color: null } );
					fragmentsLength ++;
				}
				fragments.splice( fragment ++, 0, { text: textRight, type: '=', color: null } );
				fragmentsLength ++;
			}
		}

		// Debug log
		if ( this.config.debug === true ) {
			this.debugFragments( 'Fragments' );
		}

		return;
	};


	/**
	 * Create html formatted diff code from diff fragments.
	 *
	 * @param[in] array fragments Fragments array, abstraction layer for diff code
	 * @param string|undefined version
	 *   Output version: 'new' or 'old': only text from new or old version, used for unit tests
	 * @param[out] string html Html code of diff
	 */
	this.getDiffHtml = function ( version ) {

		var fragments = this.fragments;

		// No change, only one unchanged block in containers
		if ( fragments.length === 5 && fragments[2].type === '=' ) {
			this.html = '';
			return;
		}

		// Cycle through fragments
		var htmlFragments = [];
		var fragmentsLength = fragments.length;
		for ( var fragment = 0; fragment < fragmentsLength; fragment ++ ) {
			var text = fragments[fragment].text;
			var type = fragments[fragment].type;
			var color = fragments[fragment].color;
			var html = '';

			// Test if text is blanks-only or a single character
			var blank = false;
			if ( text !== '' ) {
				blank = this.config.regExp.blankBlock.test( text );
			}

			// Add container start markup
			if ( type === '{' ) {
				html = this.config.htmlCode.containerStart;
			}

			// Add container end markup
			else if ( type === '}' ) {
				html = this.config.htmlCode.containerEnd;
			}

			// Add fragment start markup
			if ( type === '[' ) {
				html = this.config.htmlCode.fragmentStart;
			}

			// Add fragment end markup
			else if ( type === ']' ) {
				html = this.config.htmlCode.fragmentEnd;
			}

			// Add fragment separator markup
			else if ( type === ',' ) {
				html = this.config.htmlCode.separator;
			}

			// Add omission markup
			if ( type === '~' ) {
				html = this.config.htmlCode.omittedChars;
			}

			// Add omission markup
			if ( type === ' ~' ) {
				html = ' ' + this.config.htmlCode.omittedChars;
			}

			// Add omission markup
			if ( type === '~ ' ) {
				html = this.config.htmlCode.omittedChars + ' ';
			}

			// Add colored left-pointing block start markup
			else if ( type === '(<' ) {
				if ( version !== 'old' ) {

					// Get title
					var title;
					if ( this.config.noUnicodeSymbols === true ) {
						title = this.config.msg['wiked-diff-block-left-nounicode'];
					}
					else {
						title = this.config.msg['wiked-diff-block-left'];
					}

					// Get html
					if ( this.config.coloredBlocks === true ) {
						html = this.config.htmlCode.blockColoredStart;
					}
					else {
						html = this.config.htmlCode.blockStart;
					}
					html = this.htmlCustomize( html, color, title );
				}
			}

			// Add colored right-pointing block start markup
			else if ( type === '(>' ) {
				if ( version !== 'old' ) {

					// Get title
					var title;
					if ( this.config.noUnicodeSymbols === true ) {
						title = this.config.msg['wiked-diff-block-right-nounicode'];
					}
					else {
						title = this.config.msg['wiked-diff-block-right'];
					}

					// Get html
					if ( this.config.coloredBlocks === true ) {
						html = this.config.htmlCode.blockColoredStart;
					}
					else {
						html = this.config.htmlCode.blockStart;
					}
					html = this.htmlCustomize( html, color, title );
				}
			}

			// Add colored block end markup
			else if ( type === ' )' ) {
				if ( version !== 'old' ) {
					html = this.config.htmlCode.blockEnd;
				}
			}

			// Add '=' (unchanged) text and moved block
			if ( type === '=' ) {
				text = this.htmlEscape( text );
				if ( color !== null ) {
					if ( version !== 'old' ) {
						html = this.markupBlanks( text, true );
					}
				}
				else {
					html = this.markupBlanks( text );
				}
			}

			// Add '-' text
			else if ( type === '-' ) {
				if ( version !== 'new' ) {

					// For old version skip '-' inside moved group
					if ( version !== 'old' || color === null ) {
						text = this.htmlEscape( text );
						text = this.markupBlanks( text, true );
						if ( blank === true ) {
							html = this.config.htmlCode.deleteStartBlank;
						}
						else {
							html = this.config.htmlCode.deleteStart;
						}
						html += text + this.config.htmlCode.deleteEnd;
					}
				}
			}

			// Add '+' text
			else if ( type === '+' ) {
				if ( version !== 'old' ) {
					text = this.htmlEscape( text );
					text = this.markupBlanks( text, true );
					if ( blank === true ) {
						html = this.config.htmlCode.insertStartBlank;
					}
					else {
						html = this.config.htmlCode.insertStart;
					}
					html += text + this.config.htmlCode.insertEnd;
				}
			}

			// Add '<' and '>' code
			else if ( type === '<' || type === '>' ) {
				if ( version !== 'new' ) {

					// Display as deletion at original position
					if ( this.config.showBlockMoves === false || version === 'old' ) {
						text = this.htmlEscape( text );
						text = this.markupBlanks( text, true );
						if ( version === 'old' ) {
							if ( this.config.coloredBlocks === true ) {
								html =
									this.htmlCustomize( this.config.htmlCode.blockColoredStart, color ) +
									text +
									this.config.htmlCode.blockEnd;
							}
							else {
								html =
									this.htmlCustomize( this.config.htmlCode.blockStart, color ) +
									text +
									this.config.htmlCode.blockEnd;
							}
						}
						else {
							if ( blank === true ) {
								html =
									this.config.htmlCode.deleteStartBlank +
									text +
									this.config.htmlCode.deleteEnd;
							}
							else {
								html = this.config.htmlCode.deleteStart + text + this.config.htmlCode.deleteEnd;
							}
						}
					}

					// Display as mark
					else {
						if ( type === '<' ) {
							if ( this.config.coloredBlocks === true ) {
								html = this.htmlCustomize( this.config.htmlCode.markLeftColored, color, text );
							}
							else {
								html = this.htmlCustomize( this.config.htmlCode.markLeft, color, text );
							}
						}
						else {
							if ( this.config.coloredBlocks === true ) {
								html = this.htmlCustomize( this.config.htmlCode.markRightColored, color, text );
							}
							else {
								html = this.htmlCustomize( this.config.htmlCode.markRight, color, text );
							}
						}
					}
				}
			}
			htmlFragments.push( html );
		}

		// Join fragments
		this.html = htmlFragments.join( '' );

		return;
	};


	/**
	 * Customize html code fragments.
	 * Replaces:
	 *   {number}:    class/color/block/mark/id number
	 *   {title}:     title attribute (popup)
	 *   {nounicode}: noUnicodeSymbols fallback
	 *   input: html, number: block number, title: title attribute (popup) text
	 *
	 * @param string html Html code to be customized
	 * @return string Customized html code
	 */
	this.htmlCustomize = function ( html, number, title ) {

		// Replace {number} with class/color/block/mark/id number
		html = html.replace( /\{number\}/g, number);

		// Replace {nounicode} with wikEdDiffNoUnicode class name
		if ( this.config.noUnicodeSymbols === true ) {
			html = html.replace( /\{nounicode\}/g, ' wikEdDiffNoUnicode');
		}
		else {
			html = html.replace( /\{nounicode\}/g, '');
		}

		// Shorten title text, replace {title}
		if ( title !== undefined ) {
			var max = 512;
			var end = 128;
			var gapMark = ' [...] ';
			if ( title.length > max ) {
				title =
					title.substr( 0, max - gapMark.length - end ) +
					gapMark +
					title.substr( title.length - end );
			}
			title = this.htmlEscape( title );
			title = title.replace( /\t/g, '&nbsp;&nbsp;');
			title = title.replace( /  /g, '&nbsp;&nbsp;');
			html = html.replace( /\{title\}/, title);
		}
		return html;
	};


	/**
	 * Replace html-sensitive characters in output text with character entities.
	 *
	 * @param string html Html code to be escaped
	 * @return string Escaped html code
	 */
	this.htmlEscape = function ( html ) {

		html = html.replace( /&/g, '&amp;');
		html = html.replace( /</g, '&lt;');
		html = html.replace( />/g, '&gt;');
		html = html.replace( /"/g, '&quot;');
		return html;
	};


	/**
	 * Markup tabs, newlines, and spaces in diff fragment text.
	 *
	 * @param bool highlight Highlight newlines and spaces in addition to tabs
	 * @param string html Text code to be marked-up
	 * @return string Marked-up text
	 */
	this.markupBlanks = function ( html, highlight ) {

		if ( highlight === true ) {
			html = html.replace( / /g, this.config.htmlCode.space);
			html = html.replace( /\n/g, this.config.htmlCode.newline);
		}
		html = html.replace( /\t/g, this.config.htmlCode.tab);
		return html;
	};


	/**
	 * Count real words in text.
	 *
	 * @param string text Text for word counting
	 * @return int Number of words in text
	 */
	this.wordCount = function ( text ) {

		return ( text.match( this.config.regExp.countWords ) || [] ).length;
	};


	/**
	 * Test diff code for consistency with input versions.
	 * Prints results to debug console.
	 *
	 * @param[in] WikEdDiffText newText, oldText Text objects
	 */
	this.unitTests = function () {

		// Check if output is consistent with new text
		this.getDiffHtml( 'new' );
		var diff = this.html.replace( /<[^>]*>/g, '');
		var text = this.htmlEscape( this.newText.text );
		if ( diff !== text ) {
			console.log(
				'Error: wikEdDiff unit test failure: diff not consistent with new text version!'
			);
			this.error = true;
			console.log( 'new text:\n', text );
			console.log( 'new diff:\n', diff );
		}
		else {
			console.log( 'OK: wikEdDiff unit test passed: diff consistent with new text.' );
		}

		// Check if output is consistent with old text
		this.getDiffHtml( 'old' );
		var diff = this.html.replace( /<[^>]*>/g, '');
		var text = this.htmlEscape( this.oldText.text );
		if ( diff !== text ) {
			console.log(
				'Error: wikEdDiff unit test failure: diff not consistent with old text version!'
			);
			this.error = true;
			console.log( 'old text:\n', text );
			console.log( 'old diff:\n', diff );
		}
		else {
			console.log( 'OK: wikEdDiff unit test passed: diff consistent with old text.' );
		}

		return;
	};


	/**
	 * Dump blocks object to browser console.
	 *
	 * @param string name Block name
	 * @param[in] array blocks Blocks table object
	 */
	this.debugBlocks = function ( name, blocks ) {

		if ( blocks === undefined ) {
			blocks = this.blocks;
		}
		var dump =
			'\ni \toldBl \tnewBl \toldNm \tnewNm \toldSt \tcount \tuniq' +
			'\twords \tchars \ttype \tsect \tgroup \tfixed \tmoved \ttext\n';
		var blocksLength = blocks.length;
		for ( var i = 0; i < blocksLength; i ++ ) {
			dump +=
				i + ' \t' + blocks[i].oldBlock + ' \t' + blocks[i].newBlock + ' \t' +
				blocks[i].oldNumber + ' \t' + blocks[i].newNumber + ' \t' + blocks[i].oldStart + ' \t' +
				blocks[i].count + ' \t' + blocks[i].unique + ' \t' + blocks[i].words + ' \t' +
				blocks[i].chars + ' \t' + blocks[i].type + ' \t' + blocks[i].section + ' \t' +
				blocks[i].group + ' \t' + blocks[i].fixed + ' \t' + blocks[i].moved + ' \t' +
				this.debugShortenText( blocks[i].text ) + '\n';
		}
		console.log( name + ':\n' + dump );
	};


	/**
	 * Dump groups object to browser console.
	 *
	 * @param string name Group name
	 * @param[in] array groups Groups table object
	 */
	this.debugGroups = function ( name, groups ) {

		if ( groups === undefined ) {
			groups = this.groups;
		}
		var dump =
			'\ni \toldNm \tblSta \tblEnd \tuniq \tmaxWo' +
			'\twords \tchars \tfixed \toldNm \tmFrom \tcolor\n';
		var groupsLength = groupsLength;
		for ( var i = 0; i < groups.length; i ++ ) {
			dump +=
				i + ' \t' + groups[i].oldNumber + ' \t' + groups[i].blockStart + ' \t' +
				groups[i].blockEnd + ' \t' + groups[i].unique + ' \t' + groups[i].maxWords + ' \t' +
				groups[i].words + ' \t' + groups[i].chars + ' \t' + groups[i].fixed + ' \t' +
				groups[i].oldNumber + ' \t' + groups[i].movedFrom + ' \t' + groups[i].color + '\n';
		}
		console.log( name + ':\n' + dump );
	};


	/**
	 * Dump fragments array to browser console.
	 *
	 * @param string name Fragments name
	 * @param[in] array fragments Fragments array
	 */
	this.debugFragments = function ( name ) {

		var fragments = this.fragments;
		var dump = '\ni \ttype \tcolor \ttext\n';
		var fragmentsLength = fragments.length;
		for ( var i = 0; i < fragmentsLength; i ++ ) {
			dump +=
				i + ' \t"' + fragments[i].type + '" \t' + fragments[i].color + ' \t' +
				this.debugShortenText( fragments[i].text, 120, 40 ) + '\n';
		}
		console.log( name + ':\n' + dump );
	};


	/**
	 * Dump borders array to browser console.
	 *
	 * @param string name Arrays name
	 * @param[in] array border Match border array
	 */
	this.debugBorders = function ( name, borders ) {

		var dump = '\ni \t[ new \told ]\n';
		var bordersLength = borders.length;
		for ( var i = 0; i < bordersLength; i ++ ) {
			dump += i + ' \t[ ' + borders[i][0] + ' \t' + borders[i][1] + ' ]\n';
		}
		console.log( name, dump );
	};


	/**
	 * Shorten text for dumping.
	 *
	 * @param string text Text to be shortened
	 * @param int max Max length of (shortened) text
	 * @param int end Length of trailing fragment of shortened text
	 * @return string Shortened text
	 */
	this.debugShortenText = function ( text, max, end ) {

		if ( typeof text !== 'string' ) {
			text = text.toString();
		}
		text = text.replace( /\n/g, '\\n');
		text = text.replace( /\t/g, '  ');
		if ( max === undefined ) {
			max = 50;
		}
		if ( end === undefined ) {
			end = 15;
		}
		if ( text.length > max ) {
			text = text.substr( 0, max - 1 - end ) + '…' + text.substr( text.length - end );
		}
		return '"' + text + '"';
	};


	/**
	 * Start timer 'label', analogous to JavaScript console timer.
	 * Usage: this.time( 'label' );
	 *
	 * @param string label Timer label
	 * @param[out] array timer Current time in milliseconds (float)
	 */
	this.time = function ( label ) {

		this.timer[label] = new Date().getTime();
		return;
	};


	/**
	 * Stop timer 'label', analogous to JavaScript console timer.
	 * Logs time in milliseconds since start to browser console.
	 * Usage: this.timeEnd( 'label' );
	 *
	 * @param string label Timer label
	 * @param bool noLog Do not log result
	 * @return float Time in milliseconds
	 */
	this.timeEnd = function ( label, noLog ) {

		var diff = 0;
		if ( this.timer[label] !== undefined ) {
			var start = this.timer[label];
			var stop = new Date().getTime();
			diff = stop - start;
			this.timer[label] = undefined;
			if ( noLog !== true ) {
				console.log( label + ': ' + diff.toFixed( 2 ) + ' ms' );
			}
		}
		return diff;
	};


	/**
	 * Log recursion timer results to browser console.
	 * Usage: this.timeRecursionEnd();
	 *
	 * @param string text Text label for output
	 * @param[in] array recursionTimer Accumulated recursion times
	 */
	this.timeRecursionEnd = function ( text ) {

		if ( this.recursionTimer.length > 1 ) {

			// Subtract times spent in deeper recursions
			var timerEnd = this.recursionTimer.length - 1;
			for ( var i = 0; i < timerEnd; i ++ ) {
				this.recursionTimer[i] -= this.recursionTimer[i + 1];
			}

			// Log recursion times
			var timerLength = this.recursionTimer.length;
			for ( var i = 0; i < timerLength; i ++ ) {
				console.log( text + ' recursion ' + i + ': ' + this.recursionTimer[i].toFixed( 2 ) + ' ms' );
			}
		}
		this.recursionTimer = [];
		return;
	};


	/**
	 * Log variable values to debug console.
	 * Usage: this.debug( 'var', var );
	 *
	 * @param string name Object identifier
	 * @param mixed|undefined name Object to be logged
	 */
	this.debug = function ( name, object ) {

		if ( object === undefined ) {
			console.log( name );
		}
		else {
			console.log( name + ': ' + object );
		}
		return;
	};


/**
 * Add script to document head.
 *
 * @param string code JavaScript code
 */
	this.addScript = function ( code ) {

		if ( document.getElementById( 'wikEdDiffBlockHandler' ) === null ) {
			var script = document.createElement( 'script' );
			script.id = 'wikEdDiffBlockHandler';
			if ( script.innerText !== undefined ) {
				script.innerText = code;
			}
			else {
				script.textContent = code;
			}
			document.getElementsByTagName( 'head' )[0].appendChild( script );
		}
		return;
	};


/**
 * Add stylesheet to document head, cross-browser >= IE6.
 *
 * @param string css CSS code
 */
	this.addStyleSheet = function ( css ) {

		if ( document.getElementById( 'wikEdDiffStyles' ) === null ) {

			// Replace mark symbols
			css = css.replace( /\{cssMarkLeft\}/g, this.config.cssMarkLeft);
			css = css.replace( /\{cssMarkRight\}/g, this.config.cssMarkRight);

			var style = document.createElement( 'style' );
			style.id = 'wikEdDiffStyles';
			style.type = 'text/css';
			if ( style.styleSheet !== undefined ) {
				style.styleSheet.cssText = css;
			}
			else {
				style.appendChild( document.createTextNode( css ) );
			}
			document.getElementsByTagName( 'head' )[0].appendChild( style );
		}
		return;
	};


/**
 * Recursive deep copy from target over source for customization import.
 *
 * @param object source Source object
 * @param object target Target object
 */
	this.deepCopy = function ( source, target ) {

		for ( var key in source ) {
			if ( Object.prototype.hasOwnProperty.call( source, key ) === true ) {
				if ( typeof source[key] === 'object' ) {
					this.deepCopy( source[key], target[key] );
				}
				else {
					target[key] = source[key];
				}
			}
		}
		return;
	};

	// Initialze WikEdDiff object
	this.init();
};


/**
 * Data and methods for single text version (old or new one).
 *
 * @class WikEdDiffText
 */
WikEdDiff.WikEdDiffText = function ( text, parent ) {

	/** @var WikEdDiff parent Parent object for configuration settings and debugging methods */
	this.parent = parent;

	/** @var string text Text of this version */
	this.text = null;

	/** @var array tokens Tokens list */
	this.tokens = [];

	/** @var int first, last First and last index of tokens list */
	this.first = null;
	this.last = null;

	/** @var array words Word counts for version text */
	this.words = {};


	/**
	 * Constructor, initialize text object.
	 *
	 * @param string text Text of version
	 * @param WikEdDiff parent Parent, for configuration settings and debugging methods
	 */
	this.init = function () {

		if ( typeof text !== 'string' ) {
			text = text.toString();
		}

		// IE / Mac fix
		this.text = text.replace( /\r\n?/g, '\n');

		// Parse and count words and chunks for identification of unique real words
		if ( this.parent.config.timer === true ) {
			this.parent.time( 'wordParse' );
		}
		this.wordParse( this.parent.config.regExp.countWords );
		this.wordParse( this.parent.config.regExp.countChunks );
		if ( this.parent.config.timer === true ) {
			this.parent.timeEnd( 'wordParse' );
		}
		return;
	};


	/**
	 * Parse and count words and chunks for identification of unique words.
	 *
	 * @param string regExp Regular expression for counting words
	 * @param[in] string text Text of version
	 * @param[out] array words Number of word occurrences
	 */
	this.wordParse = function ( regExp ) {

		var regExpMatch = this.text.match( regExp );
		if ( regExpMatch !== null ) {
			var matchLength = regExpMatch.length;
			for (var i = 0; i < matchLength; i ++) {
				var word = regExpMatch[i];
				if ( Object.prototype.hasOwnProperty.call( this.words, word ) === false ) {
					this.words[word] = 1;
				}
				else {
					this.words[word] ++;
				}
			}
		}
		return;
	};


	/**
	 * Split text into paragraph, line, sentence, chunk, word, or character tokens.
	 *
	 * @param string level Level of splitting: paragraph, line, sentence, chunk, word, or character
	 * @param int|null token Index of token to be split, otherwise uses full text
	 * @param[in] string text Full text to be split
	 * @param[out] array tokens Tokens list
	 * @param[out] int first, last First and last index of tokens list
	 */
	this.splitText = function ( level, token ) {

		var prev = null;
		var next = null;
		var current = this.tokens.length;
		var first = current;
		var text = '';

		// Split full text or specified token
		if ( token === undefined ) {
			text = this.text;
		}
		else {
			prev = this.tokens[token].prev;
			next = this.tokens[token].next;
			text = this.tokens[token].token;
		}

		// Split text into tokens, regExp match as separator
		var number = 0;
		var split = [];
		var regExpMatch;
		var lastIndex = 0;
		var regExp = this.parent.config.regExp.split[level];
		while ( ( regExpMatch = regExp.exec( text ) ) !== null ) {
			if ( regExpMatch.index > lastIndex ) {
				split.push( text.substring( lastIndex, regExpMatch.index ) );
			}
			split.push( regExpMatch[0] );
			lastIndex = regExp.lastIndex;
		}
		if ( lastIndex < text.length ) {
			split.push( text.substring( lastIndex ) );
		}

		// Cycle through new tokens
		var splitLength = split.length;
		for ( var i = 0; i < splitLength; i ++ ) {

			// Insert current item, link to previous
			this.tokens.push( {
				token:   split[i],
				prev:    prev,
				next:    null,
				link:    null,
				number:  null,
				unique:  false
			} );
			number ++;

			// Link previous item to current
			if ( prev !== null ) {
				this.tokens[prev].next = current;
			}
			prev = current;
			current ++;
		}

		// Connect last new item and existing next item
		if ( number > 0 && token !== undefined ) {
			if ( prev !== null ) {
				this.tokens[prev].next = next;
			}
			if ( next !== null ) {
				this.tokens[next].prev = prev;
			}
		}

		// Set text first and last token index
		if ( number > 0 ) {

			// Initial text split
			if ( token === undefined ) {
				this.first = 0;
				this.last = prev;
			}

			// First or last token has been split
			else {
				if ( token === this.first ) {
					this.first = first;
				}
				if ( token === this.last ) {
					this.last = prev;
				}
			}
		}
		return;
	};


	/**
	 * Split unique unmatched tokens into smaller tokens.
	 *
	 * @param string level Level of splitting: line, sentence, chunk, or word
	 * @param[in] array tokens Tokens list
	 */
	this.splitRefine = function ( regExp ) {

		// Cycle through tokens list
		var i = this.first;
		while ( i !== null ) {

			// Refine unique unmatched tokens into smaller tokens
			if ( this.tokens[i].link === null ) {
				this.splitText( regExp, i );
			}
			i = this.tokens[i].next;
		}
		return;
	};


	/**
	 * Enumerate text token list before detecting blocks.
	 *
	 * @param[out] array tokens Tokens list
	 */
	this.enumerateTokens = function () {

		// Enumerate tokens list
		var number = 0;
		var i = this.first;
		while ( i !== null ) {
			this.tokens[i].number = number;
			number ++;
			i = this.tokens[i].next;
		}
		return;
	};


	/**
	 * Dump tokens object to browser console.
	 *
	 * @param string name Text name
	 * @param[in] int first, last First and last index of tokens list
	 * @param[in] array tokens Tokens list
	 */
	this.debugText = function ( name ) {

		var tokens = this.tokens;
		var dump = 'first: ' + this.first + '\tlast: ' + this.last + '\n';
		dump += '\ni \tlink \t(prev \tnext) \tuniq \t#num \t"token"\n';
		var i = this.first;
		while ( i !== null ) {
			dump +=
				i + ' \t' + tokens[i].link + ' \t(' + tokens[i].prev + ' \t' + tokens[i].next + ') \t' +
				tokens[i].unique + ' \t#' + tokens[i].number + ' \t' +
				parent.debugShortenText( tokens[i].token ) + '\n';
			i = tokens[i].next;
		}
		console.log( name + ':\n' + dump );
		return;
	};


	// Initialize WikEdDiffText object
	this.init();
};

// </syntaxhighlight>
</script>
</html>
<?php


/**
 * Indents a flat JSON string to make it more human-readable.
 *
 * Copied from: http://recursive-design.com/blog/2008/03/11/format-json-with-php/
 *
 * @param string $json The original JSON string to process.
 *
 * @return string Indented version of the original JSON string.
 */
function json_indent($json) {

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;

        // If this character is the end of an element,
        // output a new line and indent the next line.
        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }

        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element,
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }

            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }

        $prevChar = $char;
    }

    return $result;
}

/*
 * This is the license for Spyc.
The MIT License

Copyright (c) 2011 Vladimir Andersen

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
 */
/**
 * Spyc -- A Simple PHP YAML Class
 * @version 0.5.1
 * @author Vlad Andersen <vlad.andersen@gmail.com>
 * @author Chris Wanstrath <chris@ozmm.org>
 * @link http://code.google.com/p/spyc/
 * @copyright Copyright 2005-2006 Chris Wanstrath, 2006-2011 Vlad Andersen
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @package Spyc
 */

/**
 * The Simple PHP YAML Class.
 *
 * This class can be used to read a YAML file and convert its contents
 * into a PHP array.  It currently supports a very limited subsection of
 * the YAML spec.
 *
 * Usage:
 * <code>
 *   $Spyc  = new Spyc;
 *   $array = $Spyc->load($file);
 * </code>
 * or:
 * <code>
 *   $array = Spyc::YAMLLoad($file);
 * </code>
 * or:
 * <code>
 *   $array = spyc_load_file($file);
 * </code>
 * @package Spyc
 */
class Spyc {

  // SETTINGS

  const REMPTY = "\0\0\0\0\0";

  /**
   * Setting this to true will force YAMLDump to enclose any string value in
   * quotes.  False by default.
   *
   * @var bool
   */
  public $setting_dump_force_quotes = false;

  /**
   * Setting this to true will forse YAMLLoad to use syck_load function when
   * possible. False by default.
   * @var bool
   */
  public $setting_use_syck_is_possible = false;



  /**#@+
   * @access private
   * @var mixed
   */
  private $_dumpIndent;
  private $_dumpWordWrap;
  private $_containsGroupAnchor = false;
  private $_containsGroupAlias = false;
  private $path;
  private $result;
  private $LiteralPlaceHolder = '___YAML_Literal_Block___';
  private $SavedGroups = array();
  private $indent;
  /**
   * Path modifier that should be applied after adding current element.
   * @var array
   */
  private $delayedPath = array();

  /**#@+
   * @access public
   * @var mixed
   */
  public $_nodeId;

  /**
   * Load a valid YAML string to Spyc.
   * @param string $input
   * @return array
   */
  public function load ($input) {
    return $this->__loadString($input);
  }

  /**
   * Load a valid YAML file to Spyc.
   * @param string $file
   * @return array
   */
  public function loadFile ($file) {
    return $this->__load($file);
  }

  /**
   * Load YAML into a PHP array statically
   *
   * The load method, when supplied with a YAML stream (string or file),
   * will do its best to convert YAML in a file into a PHP array.  Pretty
   * simple.
   *  Usage:
   *  <code>
   *   $array = Spyc::YAMLLoad('lucky.yaml');
   *   print_r($array);
   *  </code>
   * @access public
   * @return array
   * @param string $input Path of YAML file or string containing YAML
   */
  public static function YAMLLoad($input) {
    $Spyc = new Spyc;
    return $Spyc->__load($input);
  }

  /**
   * Load a string of YAML into a PHP array statically
   *
   * The load method, when supplied with a YAML string, will do its best
   * to convert YAML in a string into a PHP array.  Pretty simple.
   *
   * Note: use this function if you don't want files from the file system
   * loaded and processed as YAML.  This is of interest to people concerned
   * about security whose input is from a string.
   *
   *  Usage:
   *  <code>
   *   $array = Spyc::YAMLLoadString("---\n0: hello world\n");
   *   print_r($array);
   *  </code>
   * @access public
   * @return array
   * @param string $input String containing YAML
   */
  public static function YAMLLoadString($input) {
    $Spyc = new Spyc;
    return $Spyc->__loadString($input);
  }

  /**
   * Dump YAML from PHP array statically
   *
   * The dump method, when supplied with an array, will do its best
   * to convert the array into friendly YAML.  Pretty simple.  Feel free to
   * save the returned string as nothing.yaml and pass it around.
   *
   * Oh, and you can decide how big the indent is and what the wordwrap
   * for folding is.  Pretty cool -- just pass in 'false' for either if
   * you want to use the default.
   *
   * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
   * you can turn off wordwrap by passing in 0.
   *
   * @access public
   * @return string
   * @param array $array PHP array
   * @param int $indent Pass in false to use the default, which is 2
   * @param int $wordwrap Pass in 0 for no wordwrap, false for default (40)
   * @param int $no_opening_dashes Do not start YAML file with "---\n"
   */
  public static function YAMLDump($array, $indent = false, $wordwrap = false, $no_opening_dashes = false) {
    $spyc = new Spyc;
    return $spyc->dump($array, $indent, $wordwrap, $no_opening_dashes);
  }


  /**
   * Dump PHP array to YAML
   *
   * The dump method, when supplied with an array, will do its best
   * to convert the array into friendly YAML.  Pretty simple.  Feel free to
   * save the returned string as tasteful.yaml and pass it around.
   *
   * Oh, and you can decide how big the indent is and what the wordwrap
   * for folding is.  Pretty cool -- just pass in 'false' for either if
   * you want to use the default.
   *
   * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
   * you can turn off wordwrap by passing in 0.
   *
   * @access public
   * @return string
   * @param array $array PHP array
   * @param int $indent Pass in false to use the default, which is 2
   * @param int $wordwrap Pass in 0 for no wordwrap, false for default (40)
   */
  public function dump($array,$indent = false,$wordwrap = false, $no_opening_dashes = false) {
    // Dumps to some very clean YAML.  We'll have to add some more features
    // and options soon.  And better support for folding.

    // New features and options.
    if ($indent === false or !is_numeric($indent)) {
      $this->_dumpIndent = 2;
    } else {
      $this->_dumpIndent = $indent;
    }

    if ($wordwrap === false or !is_numeric($wordwrap)) {
      $this->_dumpWordWrap = 40;
    } else {
      $this->_dumpWordWrap = $wordwrap;
    }

    // New YAML document
    $string = "";
    if (!$no_opening_dashes) $string = "---\n";

    // Start at the base of the array and move through it.
    if ($array) {
      $array = (array)$array;
      $previous_key = -1;
      foreach ($array as $key => $value) {
        if (!isset($first_key)) $first_key = $key;
        $string .= $this->_yamlize($key,$value,0,$previous_key, $first_key, $array);
        $previous_key = $key;
      }
    }
    return $string;
  }

  /**
   * Attempts to convert a key / value array item to YAML
   * @access private
   * @return string
   * @param $key The name of the key
   * @param $value The value of the item
   * @param $indent The indent of the current node
   */
  private function _yamlize($key,$value,$indent, $previous_key = -1, $first_key = 0, $source_array = null) {
    if (is_array($value)) {
      if (empty ($value))
        return $this->_dumpNode($key, array(), $indent, $previous_key, $first_key, $source_array);
      // It has children.  What to do?
      // Make it the right kind of item
      $string = $this->_dumpNode($key, self::REMPTY, $indent, $previous_key, $first_key, $source_array);
      // Add the indent
      $indent += $this->_dumpIndent;
      // Yamlize the array
      $string .= $this->_yamlizeArray($value,$indent);
    } elseif (!is_array($value)) {
      // It doesn't have children.  Yip.
      $string = $this->_dumpNode($key, $value, $indent, $previous_key, $first_key, $source_array);
    }
    return $string;
  }

  /**
   * Attempts to convert an array to YAML
   * @access private
   * @return string
   * @param $array The array you want to convert
   * @param $indent The indent of the current level
   */
  private function _yamlizeArray($array,$indent) {
    if (is_array($array)) {
      $string = '';
      $previous_key = -1;
      foreach ($array as $key => $value) {
        if (!isset($first_key)) $first_key = $key;
        $string .= $this->_yamlize($key, $value, $indent, $previous_key, $first_key, $array);
        $previous_key = $key;
      }
      return $string;
    } else {
      return false;
    }
  }

  /**
   * Returns YAML from a key and a value
   * @access private
   * @return string
   * @param $key The name of the key
   * @param $value The value of the item
   * @param $indent The indent of the current node
   */
  private function _dumpNode($key, $value, $indent, $previous_key = -1, $first_key = 0, $source_array = null) {
    // do some folding here, for blocks
    if (is_string ($value) && ((strpos($value,"\n") !== false || strpos($value,": ") !== false || strpos($value,"- ") !== false ||
      strpos($value,"*") !== false || strpos($value,"#") !== false || strpos($value,"<") !== false || strpos($value,">") !== false || strpos ($value, '  ') !== false ||
      strpos($value,"[") !== false || strpos($value,"]") !== false || strpos($value,"{") !== false || strpos($value,"}") !== false) || strpos($value,"&") !== false || strpos($value, "'") !== false || strpos($value, "!") === 0 ||
      substr ($value, -1, 1) == ':')
    ) {
      $value = $this->_doLiteralBlock($value,$indent);
    } else {
      $value  = $this->_doFolding($value,$indent);
    }

    if ($value === array()) $value = '[ ]';
    if ($value === "") $value = '""';
    if (self::isTranslationWord($value)) {
      $value = $this->_doLiteralBlock($value, $indent);
    }
    if (trim ($value) != $value)
       $value = $this->_doLiteralBlock($value,$indent);

    if (is_bool($value)) {
       $value = $value ? "true" : "false";
    }

    if ($value === null) $value = 'null';
    if ($value === "'" . self::REMPTY . "'") $value = null;

    $spaces = str_repeat(' ',$indent);

    //if (is_int($key) && $key - 1 == $previous_key && $first_key===0) {
    if (is_array ($source_array) && array_keys($source_array) === range(0, count($source_array) - 1)) {
      // It's a sequence
      $string = $spaces.'- '.$value."\n";
    } else {
      // if ($first_key===0)  throw new Exception('Keys are all screwy.  The first one was zero, now it\'s "'. $key .'"');
      // It's mapped
      if (strpos($key, ":") !== false || strpos($key, "#") !== false) { $key = '"' . $key . '"'; }
      $string = rtrim ($spaces.$key.': '.$value)."\n";
    }
    return $string;
  }

  /**
   * Creates a literal block for dumping
   * @access private
   * @return string
   * @param $value
   * @param $indent int The value of the indent
   */
  private function _doLiteralBlock($value,$indent) {
    if ($value === "\n") return '\n';
    if (strpos($value, "\n") === false && strpos($value, "'") === false) {
      return sprintf ("'%s'", $value);
    }
    if (strpos($value, "\n") === false && strpos($value, '"') === false) {
      return sprintf ('"%s"', $value);
    }
    $exploded = explode("\n",$value);
    $newValue = '|';
    $indent  += $this->_dumpIndent;
    $spaces   = str_repeat(' ',$indent);
    foreach ($exploded as $line) {
      $newValue .= "\n" . $spaces . ($line);
    }
    return $newValue;
  }

  /**
   * Folds a string of text, if necessary
   * @access private
   * @return string
   * @param $value The string you wish to fold
   */
  private function _doFolding($value,$indent) {
    // Don't do anything if wordwrap is set to 0

    if ($this->_dumpWordWrap !== 0 && is_string ($value) && strlen($value) > $this->_dumpWordWrap) {
      $indent += $this->_dumpIndent;
      $indent = str_repeat(' ',$indent);
      $wrapped = wordwrap($value,$this->_dumpWordWrap,"\n$indent");
      $value   = ">\n".$indent.$wrapped;
    } else {
      if ($this->setting_dump_force_quotes && is_string ($value) && $value !== self::REMPTY)
        $value = '"' . $value . '"';
      if (is_numeric($value) && is_string($value))
        $value = '"' . $value . '"';
    }


    return $value;
  }

  private function isTrueWord($value) {
    $words = self::getTranslations(array('true', 'on', 'yes', 'y'));
    return in_array($value, $words, true);
  }

  private function isFalseWord($value) {
    $words = self::getTranslations(array('false', 'off', 'no', 'n'));
    return in_array($value, $words, true);
  }

  private function isNullWord($value) {
    $words = self::getTranslations(array('null', '~'));
    return in_array($value, $words, true);
  }

  private function isTranslationWord($value) {
    return (
      self::isTrueWord($value)  ||
      self::isFalseWord($value) ||
      self::isNullWord($value)
    );
  }

  /**
   * Coerce a string into a native type
   * Reference: http://yaml.org/type/bool.html
   * TODO: Use only words from the YAML spec.
   * @access private
   * @param $value The value to coerce
   */
  private function coerceValue(&$value) {
    if (self::isTrueWord($value)) {
      $value = true;
    } else if (self::isFalseWord($value)) {
      $value = false;
    } else if (self::isNullWord($value)) {
      $value = null;
    }
  }

  /**
   * Given a set of words, perform the appropriate translations on them to
   * match the YAML 1.1 specification for type coercing.
   * @param $words The words to translate
   * @access private
   */
  private static function getTranslations(array $words) {
    $result = array();
    foreach ($words as $i) {
      $result = array_merge($result, array(ucfirst($i), strtoupper($i), strtolower($i)));
    }
    return $result;
  }

  // LOADING FUNCTIONS

  private function __load($input) {
    $Source = $this->loadFromSource($input);
    return $this->loadWithSource($Source);
  }

  private function __loadString($input) {
    $Source = $this->loadFromString($input);
    return $this->loadWithSource($Source);
  }

  private function loadWithSource($Source) {
    if (empty ($Source)) return array();
    if ($this->setting_use_syck_is_possible && function_exists ('syck_load')) {
      $array = syck_load (implode ("\n", $Source));
      return is_array($array) ? $array : array();
    }

    $this->path = array();
    $this->result = array();

    $cnt = count($Source);
    for ($i = 0; $i < $cnt; $i++) {
      $line = $Source[$i];

      $this->indent = strlen($line) - strlen(ltrim($line));
      $tempPath = $this->getParentPathByIndent($this->indent);
      $line = self::stripIndent($line, $this->indent);
      if (self::isComment($line)) continue;
      if (self::isEmpty($line)) continue;
      $this->path = $tempPath;

      $literalBlockStyle = self::startsLiteralBlock($line);
      if ($literalBlockStyle) {
        $line = rtrim ($line, $literalBlockStyle . " \n");
        $literalBlock = '';
        $line .= ' '.$this->LiteralPlaceHolder;
        $literal_block_indent = strlen($Source[$i+1]) - strlen(ltrim($Source[$i+1]));
        while (++$i < $cnt && $this->literalBlockContinues($Source[$i], $this->indent)) {
          $literalBlock = $this->addLiteralLine($literalBlock, $Source[$i], $literalBlockStyle, $literal_block_indent);
        }
        $i--;
      }

      // Strip out comments
      if (strpos ($line, '#')) {
        $line = preg_replace('/\s*#([^"\']+)$/','',$line);
      }

      while (++$i < $cnt && self::greedilyNeedNextLine($line)) {
        $line = rtrim ($line, " \n\t\r") . ' ' . ltrim ($Source[$i], " \t");
      }
      $i--;

      $lineArray = $this->_parseLine($line);

      if ($literalBlockStyle)
        $lineArray = $this->revertLiteralPlaceHolder ($lineArray, $literalBlock);

      $this->addArray($lineArray, $this->indent);

      foreach ($this->delayedPath as $indent => $delayedPath)
        $this->path[$indent] = $delayedPath;

      $this->delayedPath = array();

    }
    return $this->result;
  }

  private function loadFromSource ($input) {
    if (!empty($input) && strpos($input, "\n") === false && file_exists($input))
      $input = file_get_contents($input);

    return $this->loadFromString($input);
  }

  private function loadFromString ($input) {
    $lines = explode("\n",$input);
    foreach ($lines as $k => $_) {
      $lines[$k] = rtrim ($_, "\r");
    }
    return $lines;
  }

  /**
   * Parses YAML code and returns an array for a node
   * @access private
   * @return array
   * @param string $line A line from the YAML file
   */
  private function _parseLine($line) {
    if (!$line) return array();
    $line = trim($line);
    if (!$line) return array();

    $array = array();

    $group = $this->nodeContainsGroup($line);
    if ($group) {
      $this->addGroup($line, $group);
      $line = $this->stripGroup ($line, $group);
    }

    if ($this->startsMappedSequence($line))
      return $this->returnMappedSequence($line);

    if ($this->startsMappedValue($line))
      return $this->returnMappedValue($line);

    if ($this->isArrayElement($line))
     return $this->returnArrayElement($line);

    if ($this->isPlainArray($line))
     return $this->returnPlainArray($line);


    return $this->returnKeyValuePair($line);

  }

  /**
   * Finds the type of the passed value, returns the value as the new type.
   * @access private
   * @param string $value
   * @return mixed
   */
  private function _toType($value) {
    if ($value === '') return "";
    $first_character = $value[0];
    $last_character = substr($value, -1, 1);

    $is_quoted = false;
    do {
      if (!$value) break;
      if ($first_character != '"' && $first_character != "'") break;
      if ($last_character != '"' && $last_character != "'") break;
      $is_quoted = true;
    } while (0);

    if ($is_quoted) {
      $value = str_replace('\n', "\n", $value);
      return strtr(substr ($value, 1, -1), array ('\\"' => '"', '\'\'' => '\'', '\\\'' => '\''));
    }

    if (strpos($value, ' #') !== false && !$is_quoted)
      $value = preg_replace('/\s+#(.+)$/','',$value);

    if ($first_character == '[' && $last_character == ']') {
      // Take out strings sequences and mappings
      $innerValue = trim(substr ($value, 1, -1));
      if ($innerValue === '') return array();
      $explode = $this->_inlineEscape($innerValue);
      // Propagate value array
      $value  = array();
      foreach ($explode as $v) {
        $value[] = $this->_toType($v);
      }
      return $value;
    }

    if (strpos($value,': ')!==false && $first_character != '{') {
      $array = explode(': ',$value);
      $key   = trim($array[0]);
      array_shift($array);
      $value = trim(implode(': ',$array));
      $value = $this->_toType($value);
      return array($key => $value);
    }

    if ($first_character == '{' && $last_character == '}') {
      $innerValue = trim(substr ($value, 1, -1));
      if ($innerValue === '') return array();
      // Inline Mapping
      // Take out strings sequences and mappings
      $explode = $this->_inlineEscape($innerValue);
      // Propagate value array
      $array = array();
      foreach ($explode as $v) {
        $SubArr = $this->_toType($v);
        if (empty($SubArr)) continue;
        if (is_array ($SubArr)) {
          $array[key($SubArr)] = $SubArr[key($SubArr)]; continue;
        }
        $array[] = $SubArr;
      }
      return $array;
    }

    if ($value == 'null' || $value == 'NULL' || $value == 'Null' || $value == '' || $value == '~') {
      return null;
    }

    if ( is_numeric($value) && preg_match ('/^(-|)[1-9]+[0-9]*$/', $value) ){
      $intvalue = (int)$value;
      if ($intvalue != PHP_INT_MAX)
        $value = $intvalue;
      return $value;
    }

    if (is_numeric($value) && preg_match('/^0[xX][0-9a-fA-F]+$/', $value)) {
      // Hexadecimal value.
      return hexdec($value);
    }

    $this->coerceValue($value);

    if (is_numeric($value)) {
      if ($value === '0') return 0;
      if (rtrim ($value, 0) === $value)
        $value = (float)$value;
      return $value;
    }

    return $value;
  }

  /**
   * Used in inlines to check for more inlines or quoted strings
   * @access private
   * @return array
   */
  private function _inlineEscape($inline) {
    // There's gotta be a cleaner way to do this...
    // While pure sequences seem to be nesting just fine,
    // pure mappings and mappings with sequences inside can't go very
    // deep.  This needs to be fixed.

    $seqs = array();
    $maps = array();
    $saved_strings = array();
    $saved_empties = array();

    // Check for empty strings
    $regex = '/("")|(\'\')/';
    if (preg_match_all($regex,$inline,$strings)) {
      $saved_empties = $strings[0];
      $inline  = preg_replace($regex,'YAMLEmpty',$inline);
    }
    unset($regex);

    // Check for strings
    $regex = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';
    if (preg_match_all($regex,$inline,$strings)) {
      $saved_strings = $strings[0];
      $inline  = preg_replace($regex,'YAMLString',$inline);
    }
    unset($regex);

    // echo $inline;

    $i = 0;
    do {

    // Check for sequences
    while (preg_match('/\[([^{}\[\]]+)\]/U',$inline,$matchseqs)) {
      $seqs[] = $matchseqs[0];
      $inline = preg_replace('/\[([^{}\[\]]+)\]/U', ('YAMLSeq' . (count($seqs) - 1) . 's'), $inline, 1);
    }

    // Check for mappings
    while (preg_match('/{([^\[\]{}]+)}/U',$inline,$matchmaps)) {
      $maps[] = $matchmaps[0];
      $inline = preg_replace('/{([^\[\]{}]+)}/U', ('YAMLMap' . (count($maps) - 1) . 's'), $inline, 1);
    }

    if ($i++ >= 10) break;

    } while (strpos ($inline, '[') !== false || strpos ($inline, '{') !== false);

    $explode = explode(',',$inline);
    $explode = array_map('trim', $explode);
    $stringi = 0; $i = 0;

    while (1) {

    // Re-add the sequences
    if (!empty($seqs)) {
      foreach ($explode as $key => $value) {
        if (strpos($value,'YAMLSeq') !== false) {
          foreach ($seqs as $seqk => $seq) {
            $explode[$key] = str_replace(('YAMLSeq'.$seqk.'s'),$seq,$value);
            $value = $explode[$key];
          }
        }
      }
    }

    // Re-add the mappings
    if (!empty($maps)) {
      foreach ($explode as $key => $value) {
        if (strpos($value,'YAMLMap') !== false) {
          foreach ($maps as $mapk => $map) {
            $explode[$key] = str_replace(('YAMLMap'.$mapk.'s'), $map, $value);
            $value = $explode[$key];
          }
        }
      }
    }


    // Re-add the strings
    if (!empty($saved_strings)) {
      foreach ($explode as $key => $value) {
        while (strpos($value,'YAMLString') !== false) {
          $explode[$key] = preg_replace('/YAMLString/',$saved_strings[$stringi],$value, 1);
          unset($saved_strings[$stringi]);
          ++$stringi;
          $value = $explode[$key];
        }
      }
    }


    // Re-add the empties
    if (!empty($saved_empties)) {
      foreach ($explode as $key => $value) {
        while (strpos($value,'YAMLEmpty') !== false) {
          $explode[$key] = preg_replace('/YAMLEmpty/', '', $value, 1);
          $value = $explode[$key];
        }
      }
    }

    $finished = true;
    foreach ($explode as $key => $value) {
      if (strpos($value,'YAMLSeq') !== false) {
        $finished = false; break;
      }
      if (strpos($value,'YAMLMap') !== false) {
        $finished = false; break;
      }
      if (strpos($value,'YAMLString') !== false) {
        $finished = false; break;
      }
      if (strpos($value,'YAMLEmpty') !== false) {
        $finished = false; break;
      }
    }
    if ($finished) break;

    $i++;
    if ($i > 10)
      break; // Prevent infinite loops.
    }


    return $explode;
  }

  private function literalBlockContinues ($line, $lineIndent) {
    if (!trim($line)) return true;
    if (strlen($line) - strlen(ltrim($line)) > $lineIndent) return true;
    return false;
  }

  private function referenceContentsByAlias ($alias) {
    do {
      if (!isset($this->SavedGroups[$alias])) { echo "Bad group name: $alias."; break; }
      $groupPath = $this->SavedGroups[$alias];
      $value = $this->result;
      foreach ($groupPath as $k) {
        $value = $value[$k];
      }
    } while (false);
    return $value;
  }

  private function addArrayInline ($array, $indent) {
      $CommonGroupPath = $this->path;
      if (empty ($array)) return false;

      foreach ($array as $k => $_) {
        $this->addArray(array($k => $_), $indent);
        $this->path = $CommonGroupPath;
      }
      return true;
  }

  private function addArray ($incoming_data, $incoming_indent) {

    // print_r ($incoming_data);

    if (count ($incoming_data) > 1)
      return $this->addArrayInline ($incoming_data, $incoming_indent);

    $key = key ($incoming_data);
    $value = isset($incoming_data[$key]) ? $incoming_data[$key] : null;
    if ($key === '__!YAMLZero') $key = '0';

    if ($incoming_indent == 0 && !$this->_containsGroupAlias && !$this->_containsGroupAnchor) { // Shortcut for root-level values.
      if ($key || $key === '' || $key === '0') {
        $this->result[$key] = $value;
      } else {
        $this->result[] = $value; end ($this->result); $key = key ($this->result);
      }
      $this->path[$incoming_indent] = $key;
      return;
    }



    $history = array();
    // Unfolding inner array tree.
    $history[] = $_arr = $this->result;
    foreach ($this->path as $k) {
      $history[] = $_arr = $_arr[$k];
    }

    if ($this->_containsGroupAlias) {
      $value = $this->referenceContentsByAlias($this->_containsGroupAlias);
      $this->_containsGroupAlias = false;
    }


    // Adding string or numeric key to the innermost level or $this->arr.
    if (is_string($key) && $key == '<<') {
      if (!is_array ($_arr)) { $_arr = array (); }

      $_arr = array_merge ($_arr, $value);
    } else if ($key || $key === '' || $key === '0') {
      if (!is_array ($_arr))
        $_arr = array ($key=>$value);
      else
        $_arr[$key] = $value;
    } else {
      if (!is_array ($_arr)) { $_arr = array ($value); $key = 0; }
      else { $_arr[] = $value; end ($_arr); $key = key ($_arr); }
    }

    $reverse_path = array_reverse($this->path);
    $reverse_history = array_reverse ($history);
    $reverse_history[0] = $_arr;
    $cnt = count($reverse_history) - 1;
    for ($i = 0; $i < $cnt; $i++) {
      $reverse_history[$i+1][$reverse_path[$i]] = $reverse_history[$i];
    }
    $this->result = $reverse_history[$cnt];

    $this->path[$incoming_indent] = $key;

    if ($this->_containsGroupAnchor) {
      $this->SavedGroups[$this->_containsGroupAnchor] = $this->path;
      if (is_array ($value)) {
        $k = key ($value);
        if (!is_int ($k)) {
          $this->SavedGroups[$this->_containsGroupAnchor][$incoming_indent + 2] = $k;
        }
      }
      $this->_containsGroupAnchor = false;
    }

  }

  private static function startsLiteralBlock ($line) {
    $lastChar = substr (trim($line), -1);
    if ($lastChar != '>' && $lastChar != '|') return false;
    if ($lastChar == '|') return $lastChar;
    // HTML tags should not be counted as literal blocks.
    if (preg_match ('#<.*?>$#', $line)) return false;
    return $lastChar;
  }

  private static function greedilyNeedNextLine($line) {
    $line = trim ($line);
    if (!strlen($line)) return false;
    if (substr ($line, -1, 1) == ']') return false;
    if ($line[0] == '[') return true;
    if (preg_match ('#^[^:]+?:\s*\[#', $line)) return true;
    return false;
  }

  private function addLiteralLine ($literalBlock, $line, $literalBlockStyle, $indent = -1) {
    $line = self::stripIndent($line, $indent);
    if ($literalBlockStyle !== '|') {
        $line = self::stripIndent($line);
    }
    $line = rtrim ($line, "\r\n\t ") . "\n";
    if ($literalBlockStyle == '|') {
      return $literalBlock . $line;
    }
    if (strlen($line) == 0)
      return rtrim($literalBlock, ' ') . "\n";
    if ($line == "\n" && $literalBlockStyle == '>') {
      return rtrim ($literalBlock, " \t") . "\n";
    }
    if ($line != "\n")
      $line = trim ($line, "\r\n ") . " ";
    return $literalBlock . $line;
  }

   function revertLiteralPlaceHolder ($lineArray, $literalBlock) {
     foreach ($lineArray as $k => $_) {
      if (is_array($_))
        $lineArray[$k] = $this->revertLiteralPlaceHolder ($_, $literalBlock);
      else if (substr($_, -1 * strlen ($this->LiteralPlaceHolder)) == $this->LiteralPlaceHolder)
        $lineArray[$k] = rtrim ($literalBlock, " \r\n");
     }
     return $lineArray;
   }

  private static function stripIndent ($line, $indent = -1) {
    if ($indent == -1) $indent = strlen($line) - strlen(ltrim($line));
    return substr ($line, $indent);
  }

  private function getParentPathByIndent ($indent) {
    if ($indent == 0) return array();
    $linePath = $this->path;
    do {
      end($linePath); $lastIndentInParentPath = key($linePath);
      if ($indent <= $lastIndentInParentPath) array_pop ($linePath);
    } while ($indent <= $lastIndentInParentPath);
    return $linePath;
  }


  private function clearBiggerPathValues ($indent) {


    if ($indent == 0) $this->path = array();
    if (empty ($this->path)) return true;

    foreach ($this->path as $k => $_) {
      if ($k > $indent) unset ($this->path[$k]);
    }

    return true;
  }


  private static function isComment ($line) {
    if (!$line) return false;
    if ($line[0] == '#') return true;
    if (trim($line, " \r\n\t") == '---') return true;
    return false;
  }

  private static function isEmpty ($line) {
    return (trim ($line) === '');
  }


  private function isArrayElement ($line) {
    if (!$line || !is_scalar($line)) return false;
    if (substr($line, 0, 2) != '- ') return false;
    if (strlen ($line) > 3)
      if (substr($line,0,3) == '---') return false;

    return true;
  }

  private function isHashElement ($line) {
    return strpos($line, ':');
  }

  private function isLiteral ($line) {
    if ($this->isArrayElement($line)) return false;
    if ($this->isHashElement($line)) return false;
    return true;
  }


  private static function unquote ($value) {
    if (!$value) return $value;
    if (!is_string($value)) return $value;
    if ($value[0] == '\'') return trim ($value, '\'');
    if ($value[0] == '"') return trim ($value, '"');
    return $value;
  }

  private function startsMappedSequence ($line) {
    return (substr($line, 0, 2) == '- ' && substr ($line, -1, 1) == ':');
  }

  private function returnMappedSequence ($line) {
    $array = array();
    $key         = self::unquote(trim(substr($line,1,-1)));
    $array[$key] = array();
    $this->delayedPath = array(strpos ($line, $key) + $this->indent => $key);
    return array($array);
  }

  private function checkKeysInValue($value) {
    if (strchr('[{"\'', $value[0]) === false) {
      if (strchr($value, ': ') !== false) {
          throw new Exception('Too many keys: '.$value);
      }
    }
  }

  private function returnMappedValue ($line) {
    $this->checkKeysInValue($line);
    $array = array();
    $key         = self::unquote (trim(substr($line,0,-1)));
    $array[$key] = '';
    return $array;
  }

  private function startsMappedValue ($line) {
    return (substr ($line, -1, 1) == ':');
  }

  private function isPlainArray ($line) {
    return ($line[0] == '[' && substr ($line, -1, 1) == ']');
  }

  private function returnPlainArray ($line) {
    return $this->_toType($line);
  }

  private function returnKeyValuePair ($line) {
    $array = array();
    $key = '';
    if (strpos ($line, ': ')) {
      // It's a key/value pair most likely
      // If the key is in double quotes pull it out
      if (($line[0] == '"' || $line[0] == "'") && preg_match('/^(["\'](.*)["\'](\s)*:)/',$line,$matches)) {
        $value = trim(str_replace($matches[1],'',$line));
        $key   = $matches[2];
      } else {
        // Do some guesswork as to the key and the value
        $explode = explode(': ', $line);
        $key     = trim(array_shift($explode));
        $value   = trim(implode(': ', $explode));
        $this->checkKeysInValue($value);
      }
      // Set the type of the value.  Int, string, etc
      $value = $this->_toType($value);
      if ($key === '0') $key = '__!YAMLZero';
      $array[$key] = $value;
    } else {
      $array = array ($line);
    }
    return $array;

  }


  private function returnArrayElement ($line) {
     if (strlen($line) <= 1) return array(array()); // Weird %)
     $array = array();
     $value   = trim(substr($line,1));
     $value   = $this->_toType($value);
     if ($this->isArrayElement($value)) {
       $value = $this->returnArrayElement($value);
     }
     $array[] = $value;
     return $array;
  }


  private function nodeContainsGroup ($line) {
    $symbolsForReference = 'A-z0-9_\-';
    if (strpos($line, '&') === false && strpos($line, '*') === false) return false; // Please die fast ;-)
    if ($line[0] == '&' && preg_match('/^(&['.$symbolsForReference.']+)/', $line, $matches)) return $matches[1];
    if ($line[0] == '*' && preg_match('/^(\*['.$symbolsForReference.']+)/', $line, $matches)) return $matches[1];
    if (preg_match('/(&['.$symbolsForReference.']+)$/', $line, $matches)) return $matches[1];
    if (preg_match('/(\*['.$symbolsForReference.']+$)/', $line, $matches)) return $matches[1];
    if (preg_match ('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $matches)) return $matches[1];
    return false;

  }

  private function addGroup ($line, $group) {
    if ($group[0] == '&') $this->_containsGroupAnchor = substr ($group, 1);
    if ($group[0] == '*') $this->_containsGroupAlias = substr ($group, 1);
    //print_r ($this->path);
  }

  private function stripGroup ($line, $group) {
    $line = trim(str_replace($group, '', $line));
    return $line;
  }
}

// This is for loading the graphics.

function get_fav_icon() {
  $output = <<<'EOF'
AAABAAEAEBAAAAEAIABoBAAAFgAAACgAAAAQAAAAIAAAAAEAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA
AAAAAAAAqWVHAAAAAAAAAAAAp2FDM6ZfQaqvb1HzvYlr/72Jav+ub1HzpV5Ap6dhQzMAAAAAAAAAAKhl
RwAAAAAAAAAAAAAAAACfVjgRrGpMnMWWeP/w5MT////p////5P///t7////j//DjxP/Flnj/q2lLk6Nc
PhIAAAAAqGVHAAAAAACcTjEWs3hatuHJqv///+n//v7e/+rZuf/UspP/wZFy/8mfgP/v4sL////m/93D
pP+ydVetpF5AEAAAAACPNhkDsHJUkuHKq////+L/4cqq/8COb/+1elz/wI9x/86piv+2fF7/sXRV//v5
2f///+X/3cOk/6pnSZMAAAAArGlLLMaZev/068v/0quL/8ykhf/fxqf/8unJ//r21v///+H/5tOz/6dh
Q//9+9v////h////4//ElXf/pl9BMq1sTZTs27v/0NG5/9DPtv/8+9z///7e////3////9/////l/9i4
mf/ElXf////m////3////+L/7+LD/6RcPqWucFLd///k//7/3/+Pj4L/6+vO////4P///9/////f////
6f+4gGH/5M+w//793f/489P/+fTU////5/+tbU/vuIBi/v//6f///+H/7ezP/4qXkv/T59j/7fvk////
3////+n/tXtd/8+piv/VtJb/wpN1/76Mbf/9+9v/vIhp/biAYf3//+n////f////4v+EvtL/SYmp/3nE
4/+o4vD/5vjl/+/fv//gyKn/59S1//Hmxv/Sro///fzc/7yHaf2vcFLd///k////3////97/1/Po/0Wt
4/9Hi67/TrPm/2/N9//i9uX////h////4v/699f/9e3N////5v+tbU/trWxOk+nYuf///+P////f////
3v+N1vD/SbLo/0mOsf9ZuOX/i9bx/7/o6P///97///7f////4f/v4sL/pV0/pKtpSynFl3j////j////
3////9//+f3g/3LN9f9PufD/SY6x/02z5/9Ovvf/ndzu//3/4P///+X/w5N1/6VeQC+WRCYDsXRVk+DH
qP///+H////f////3//s+OL/jNfx/2PK+v9KmcH/Tqzb/0668f+k3+7/4MSk/6tnSJYAAAAAAAAAAJZE
Jha1e1244Meo////4////+P////f//3+4P/N7eb/c9D5/0iw5v9LsOP/Zbzl/4KbqOmjXD8OAAAAAAAA
AAAAAAAAlkQmFrF0VZTFl3j/6ti5////5P///+n////p/+z85/+pwsT/Zp+//0658P9Pv/j/UMH5qVK/
9gKpZUcAAAAAAAAAAACWRCcDq2lLKa1sTpSvcFLeuIBh/biAYf6vb1Hdq2tOkpZ2ais+0v8OUcD3MlHA
+EtRwPgE+B8AAOAHAADAAwAAgAEAAIABAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAAQAAgAEAAMAD
AADgAQAA+B8AAA==
EOF;
  return base64_decode($output);
}
function get_header() {
  $output = <<<'EOF'
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
EOF;
  return base64_decode($output);
}
