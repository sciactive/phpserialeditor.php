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
    <style type="text/css">
      textarea {
        width: 100%;
        border: 1px solid #333;
      }
      #diff_container {
        width: 100%;
        overflow: auto;
        border: 1px solid #333;
      }
      #diff {
        padding: .5em;
        white-space: pre;
        font-size: .8em;
        font-family: monospace;
      }
    </style>
    <script type="text/javascript">
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
        })
        var editor = $('#editor').on('change keyup', function(){
          if (updating)
            return;
          $.post('', {type: 'exported', 'value': editor.val(), 'language': $('input[name=language]:checked').val()}, function(data){
            updating = true;
            output.val(data);
            diff.html(pretty_php_serialized(WDiffString(original, data)));
            updating = false;
          });
        });
        serialized.trigger('change');
      });

      function pretty_php_serialized(serialized) {
        serialized = serialized.replace(/<br\/?>/g, '').replace(/&([a-z0-9#]+);/gi, '**ent($1)ent**');
        while (serialized.match(/\{[^\n]/)!==null)
          serialized = serialized.replace(/\{([^\n])/g, '{\n$1');
        while (serialized.match(/\}[^\n]/)!==null)
          serialized = serialized.replace(/\}([^\n])/g, '}\n$1');
        while (serialized.match(/[^\n]\}/)!==null)
          serialized = serialized.replace(/([^\n])\}/g, '$1\n}');
        while (serialized.match(/;[^\n]/)!==null)
          serialized = serialized.replace(/;([^\n])/g, ';\n$1');
        while (serialized.match(/\{\n\}/)!==null)
          serialized = serialized.replace(/\{\n\}/g, '{}');
        var cur_indent = 1;
        var cur_entry_index = false;
        var lines = serialized.split('\n');
        serialized = '';
        for (var i=0; i<lines.length; i++) {
          var is_a_closer = lines[i].charAt(0) == '}';
          if (is_a_closer) {
            cur_indent--;
            serialized += Array(cur_indent).join('  ')+lines[i]+'\n';
          } else {
            if (cur_entry_index)
              serialized += Array(cur_indent).join('  ')+lines[i];
            else
              serialized += lines[i]+'\n';
            cur_entry_index = !cur_entry_index;
          }
          if (lines[i].charAt(lines[i].length-1) == '{')
            cur_indent++;
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
    <div class="container">
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
      <div class="row">
        <div class="col-sm-6">
          1. Paste in serialized PHP here: <small>(<a href="javascript:void(0);" onclick="do_example();">example</a>)</small><br />
          <textarea rows="6" cols="30" id="serialized" style="height: 100px;"></textarea>
        </div>
        <div class="col-sm-6">
          3. The new serialized PHP will appear here:<br />
          <textarea rows="6" cols="30" id="output" style="height: 100px;"></textarea>
        </div>
      </div>
      <div class="row">
        <div class="col-sm-6">
          2. Then edit the value here:<br />
          <textarea rows="20" cols="30" id="editor" style="height: 500px;"></textarea>
        </div>
        <div class="col-sm-6">
          4. A colored diff will show here:<br />
          <div id="diff_container" style="height: 500px;">
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
/*

Name:    wDiff.js
Version: 0.9.9 (October 10, 2010)
Info:    http://en.wikipedia.org/wiki/User:Cacycle/diff
Code:    http://en.wikipedia.org/wiki/User:Cacycle/diff.js

JavaScript diff algorithm by [[en:User:Cacycle]] (http://en.wikipedia.org/wiki/User_talk:Cacycle).
Outputs html/css-formatted new text with highlighted deletions, inserts, and block moves.
For newline highlighting the following style rules have to be added to the document:
  .wDiffParagraph:before { content: "¶"; };

The program uses cross-browser code and should work with all modern browsers. It has been tested with:
* Mozilla Firefox 1.5.0.1
* Mozilla SeaMonkey 1.0
* Opera 8.53
* Internet Explorer 6.0.2900.2180
* Internet Explorer 7.0.5730.11
This program is also compatible with Greasemonkey

An implementation of the word-based algorithm from:

Communications of the ACM 21(4):264 (1978)
http://doi.acm.org/10.1145/359460.359467

With the following additional feature:

* Word types have been optimized for MediaWiki source texts
* Additional post-pass 5 code for resolving islands caused by adding
  two common words at the end of sequences of common words
* Additional detection of block borders and color coding of moved blocks and their original position
* Optional "intelligent" omission of unchanged parts from the output

This code is used by the MediaWiki in-browser text editors [[en:User:Cacycle/editor]] and [[en:User:Cacycle/wikEd]]
and the enhanced diff view tool wikEdDiff [[en:User:Cacycle/wikEd]].

Usage: var htmlText = WDiffString(oldText, newText);

This code has been released into the public domain.

Datastructures (abbreviations from publication):

text: an object that holds all text related datastructures
  .newWords: consecutive words of the new text (N)
  .oldWords: consecutive words of the old text (O)
  .newToOld: array pointing to corresponding word number in old text (NA)
  .oldToNew: array pointing to corresponding word number in new text (OA)
  .message:  output message for testing purposes

symbol table:
  symbols[word]: associative array (object) of detected words for passes 1 - 3, points to symbol[i]
  symbol[i]: array of objects that hold word counters and pointers:
    .newCtr:  new word occurences counter (NC)
    .oldCtr:  old word occurences counter (OC)
    .toNew:   first word occurrence in new text, points to text.newWords[i]
    .toOld:   last word occurrence in old text, points to text.oldWords[i]

block: an object that holds block move information
  blocks indexed after new text:
  .newStart:  new text word number of start of this block
  .newLength: element number of this block including non-words
  .newWords:  true word number of this block
  .newNumber: corresponding block index in old text
  .newBlock:  moved-block-number of a block that has been moved here
  .newLeft:   moved-block-number of a block that has been moved from this border leftwards
  .newRight:  moved-block-number of a block that has been moved from this border rightwards
  .newLeftIndex:  index number of a block that has been moved from this border leftwards
  .newRightIndex: index number of a block that has been moved from this border rightwards
  blocks indexed after old text:
  .oldStart:  word number of start of this block
  .oldToNew:  corresponding new text word number of start
  .oldLength: element number of this block including non-words
  .oldWords:  true word number of this block

*/


// css for change indicators
if (typeof(wDiffStyleDelete) == 'undefined') { window.wDiffStyleDelete = 'font-weight: normal; text-decoration: none; color: #fff; background-color: #990033;'; }
if (typeof(wDiffStyleInsert) == 'undefined') { window.wDiffStyleInsert = 'font-weight: normal; text-decoration: none; color: #fff; background-color: #009933;'; }
if (typeof(wDiffStyleMoved)  == 'undefined') { window.wDiffStyleMoved  = 'font-weight: bold;  color: #000; vertical-align: text-bottom; font-size: xx-small; padding: 0; border: solid 1px;'; }
if (typeof(wDiffStyleBlock)  == 'undefined') { window.wDiffStyleBlock  = [
  'color: #000; background-color: #ffff80;',
  'color: #000; background-color: #c0ffff;',
  'color: #000; background-color: #ffd0f0;',
  'color: #000; background-color: #ffe080;',
  'color: #000; background-color: #aaddff;',
  'color: #000; background-color: #ddaaff;',
  'color: #000; background-color: #ffbbbb;',
  'color: #000; background-color: #d8ffa0;',
  'color: #000; background-color: #d0d0d0;'
]; }

// html for change indicators, {number} is replaced by the block number
// {block} is replaced by the block style, class and html comments are important for shortening the output
if (typeof(wDiffHtmlMovedRight)  == 'undefined') { window.wDiffHtmlMovedRight  = '<input class="wDiffHtmlMovedRight" type="button" value="&gt;" style="' + wDiffStyleMoved + ' {block}"><!--wDiffHtmlMovedRight-->'; }
if (typeof(wDiffHtmlMovedLeft)   == 'undefined') { window.wDiffHtmlMovedLeft   = '<input class="wDiffHtmlMovedLeft" type="button" value="&lt;" style="' + wDiffStyleMoved + ' {block}"><!--wDiffHtmlMovedLeft-->'; }

if (typeof(wDiffHtmlBlockStart)  == 'undefined') { window.wDiffHtmlBlockStart  = '<span class="wDiffHtmlBlock" style="{block}">'; }
if (typeof(wDiffHtmlBlockEnd)    == 'undefined') { window.wDiffHtmlBlockEnd    = '</span><!--wDiffHtmlBlock-->'; }

if (typeof(wDiffHtmlDeleteStart) == 'undefined') { window.wDiffHtmlDeleteStart = '<span class="wDiffHtmlDelete" style="' + wDiffStyleDelete + '">'; }
if (typeof(wDiffHtmlDeleteEnd)   == 'undefined') { window.wDiffHtmlDeleteEnd   = '</span><!--wDiffHtmlDelete-->'; }

if (typeof(wDiffHtmlInsertStart) == 'undefined') { window.wDiffHtmlInsertStart = '<span class="wDiffHtmlInsert" style="' + wDiffStyleInsert + '">'; }
if (typeof(wDiffHtmlInsertEnd)   == 'undefined') { window.wDiffHtmlInsertEnd   = '</span><!--wDiffHtmlInsert-->'; }

// minimal number of real words for a moved block (0 for always displaying block move indicators)
if (typeof(wDiffBlockMinLength) == 'undefined') { window.wDiffBlockMinLength = 3; }

// exclude identical sequence starts and endings from change marking
if (typeof(wDiffWordDiff) == 'undefined') { window.wDiffWordDiff = true; }

// enable recursive diff to resolve problematic sequences
if (typeof(wDiffRecursiveDiff) == 'undefined') { window.wDiffRecursiveDiff = true; }

// enable block move display
if (typeof(wDiffShowBlockMoves) == 'undefined') { window.wDiffShowBlockMoves = true; }

// remove unchanged parts from final output

// characters before diff tag to search for previous heading, paragraph, line break, cut characters
if (typeof(wDiffHeadingBefore)   == 'undefined') { window.wDiffHeadingBefore   = 1500; }
if (typeof(wDiffParagraphBefore) == 'undefined') { window.wDiffParagraphBefore = 1500; }
if (typeof(wDiffLineBeforeMax)   == 'undefined') { window.wDiffLineBeforeMax   = 1000; }
if (typeof(wDiffLineBeforeMin)   == 'undefined') { window.wDiffLineBeforeMin   =  500; }
if (typeof(wDiffBlankBeforeMax)  == 'undefined') { window.wDiffBlankBeforeMax  = 1000; }
if (typeof(wDiffBlankBeforeMin)  == 'undefined') { window.wDiffBlankBeforeMin  =  500; }
if (typeof(wDiffCharsBefore)     == 'undefined') { window.wDiffCharsBefore     =  500; }

// characters after diff tag to search for next heading, paragraph, line break, or characters
if (typeof(wDiffHeadingAfter)   == 'undefined') { window.wDiffHeadingAfter   = 1500; }
if (typeof(wDiffParagraphAfter) == 'undefined') { window.wDiffParagraphAfter = 1500; }
if (typeof(wDiffLineAfterMax)   == 'undefined') { window.wDiffLineAfterMax   = 1000; }
if (typeof(wDiffLineAfterMin)   == 'undefined') { window.wDiffLineAfterMin   =  500; }
if (typeof(wDiffBlankAfterMax)  == 'undefined') { window.wDiffBlankAfterMax  = 1000; }
if (typeof(wDiffBlankAfterMin)  == 'undefined') { window.wDiffBlankAfterMin  =  500; }
if (typeof(wDiffCharsAfter)     == 'undefined') { window.wDiffCharsAfter     =  500; }

// maximal fragment distance to join close fragments
if (typeof(wDiffFragmentJoin)  == 'undefined') { window.wDiffFragmentJoin = 1000; }
if (typeof(wDiffOmittedChars)  == 'undefined') { window.wDiffOmittedChars = '…'; }
if (typeof(wDiffOmittedLines)  == 'undefined') { window.wDiffOmittedLines = '<hr style="height: 2px; margin: 1em 10%;">'; }
if (typeof(wDiffNoChange)      == 'undefined') { window.wDiffNoChange     = '<hr style="height: 2px; margin: 1em 20%;">'; }

// compatibility fix for old name of main function
window.StringDiff = window.WDiffString;


// WDiffString: main program
// input: oldText, newText, strings containing the texts
// returns: html diff

window.WDiffString = function(oldText, newText) {

// IE / Mac fix
  oldText = oldText.replace(/\r\n?/g, '\n');
  newText = newText.replace(/\r\n?/g, '\n');

  var text = {};
  text.newWords = [];
  text.oldWords = [];
  text.newToOld = [];
  text.oldToNew = [];
  text.message = '';
  var block = {};
  var outText = '';

// trap trivial changes: no change
  if (oldText == newText) {
    outText = newText;
    outText = WDiffEscape(outText);
    outText = WDiffHtmlFormat(outText);
    return(outText);
  }

// trap trivial changes: old text deleted
  if ( (oldText == null) || (oldText.length == 0) ) {
    outText = newText;
    outText = WDiffEscape(outText);
    outText = WDiffHtmlFormat(outText);
    outText = wDiffHtmlInsertStart + outText + wDiffHtmlInsertEnd;
    return(outText);
  }

// trap trivial changes: new text deleted
  if ( (newText == null) || (newText.length == 0) ) {
    outText = oldText;
    outText = WDiffEscape(outText);
    outText = WDiffHtmlFormat(outText);
    outText = wDiffHtmlDeleteStart + outText + wDiffHtmlDeleteEnd;
    return(outText);
  }

// split new and old text into words
  WDiffSplitText(oldText, newText, text);

// calculate diff information
  WDiffText(text);

//detect block borders and moved blocks
  WDiffDetectBlocks(text, block);

// process diff data into formatted html text
  outText = WDiffToHtml(text, block);

// IE fix
  outText = outText.replace(/> ( *)</g, '>&nbsp;$1<');

  return(outText);
};


// WDiffSplitText: split new and old text into words
// input: oldText, newText, strings containing the texts
// changes: text.newWords and text.oldWords, arrays containing the texts in arrays of words

window.WDiffSplitText = function(oldText, newText, text) {

// convert strange spaces
  oldText = oldText.replace(/[\t\u000b\u00a0\u2028\u2029]+/g, ' ');
  newText = newText.replace(/[\t\u000b\u00a0\u2028\u2029]+/g, ' ');

// split old text into words

//              /     |    |    |    |    |   |  |     |   |  |  |    |    |    | /
  var pattern = /[\w]+|\[\[|\]\]|\{\{|\}\}|\n+| +|&\w+;|'''|''|=+|\{\||\|\}|\|\-|./g;
  var result;
  do {
    result = pattern.exec(oldText);
    if (result != null) {
      text.oldWords.push(result[0]);
    }
  } while (result != null);

// split new text into words
  do {
    result = pattern.exec(newText);
    if (result != null) {
      text.newWords.push(result[0]);
    }
  } while (result != null);

  return;
};


// WDiffText: calculate diff information
// input: text.newWords and text.oldWords, arrays containing the texts as arrays of words
// optionally for recursive calls: newStart, newEnd, oldStart, oldEnd, recursionLevel
// changes: text.newToOld and text.oldToNew, arrays pointing to corresponding words

window.WDiffText = function(text, newStart, newEnd, oldStart, oldEnd, recursionLevel) {

  var symbol = [];
  var symbols = {};

// set defaults
  if (typeof(newStart) == 'undefined') { newStart = 0; }
  if (typeof(newEnd) == 'undefined') { newEnd = text.newWords.length; }
  if (typeof(oldStart) == 'undefined') { oldStart = 0; }
  if (typeof(oldEnd) == 'undefined') { oldEnd = text.oldWords.length; }
  if (typeof(recursionLevel) == 'undefined') { recursionLevel = 0; }

// limit recursion depth
  if (recursionLevel > 10) {
    return;
  }

//
// pass 1: Parse new text into symbol table
//
  for (var i = newStart; i < newEnd; i ++) {
    var word = text.newWords[i];

// preserve the native method
    if (word.indexOf('hasOwnProperty') == 0) {
      word = word.replace(/^(hasOwnProperty_*)$/, '$1_');
    }

// add new entry to symbol table
    if (symbols.hasOwnProperty(word) == false) {
      var last = symbol.length;
      symbols[word] = last;
      symbol[last] = { newCtr: 1, oldCtr: 0, toNew: i, toOld: null };
    }

// or update existing entry
    else {

// increment word counter for new text
      var hashToArray = symbols[word];
      symbol[hashToArray].newCtr ++;
    }
  }

//
// pass 2: parse old text into symbol table
//
  for (var i = oldStart; i < oldEnd; i ++) {
    var word = text.oldWords[i];

// preserve the native method
    if (word.indexOf('hasOwnProperty') == 0) {
      word = word.replace(/^(hasOwnProperty_*)$/, '$1_');
    }

// add new entry to symbol table
    if (symbols.hasOwnProperty(word) == false) {
      var last = symbol.length;
      symbols[word] = last;
      symbol[last] = { newCtr: 0, oldCtr: 1, toNew: null, toOld: i };
    }

// or update existing entry
    else {

// increment word counter for old text
      var hashToArray = symbols[word];
      symbol[hashToArray].oldCtr ++;

// add word number for old text
      symbol[hashToArray].toOld = i;
    }
  }

//
// pass 3: connect unique words
//
  for (var i = 0; i < symbol.length; i ++) {

// find words in the symbol table that occur only once in both versions
    if ( (symbol[i].newCtr == 1) && (symbol[i].oldCtr == 1) ) {
      var toNew = symbol[i].toNew;
      var toOld = symbol[i].toOld;

// do not use spaces as unique markers
      if (/^\s+$/.test(text.newWords[toNew]) == false) {

// connect from new to old and from old to new
        text.newToOld[toNew] = toOld;
        text.oldToNew[toOld] = toNew;
      }
    }
  }

//
// pass 4: connect adjacent identical words downwards
//
  for (var i = newStart; i < newEnd - 1; i ++) {

// find already connected pairs
    if (text.newToOld[i] != null) {
      var j = text.newToOld[i];

// check if the following words are not yet connected
      if ( (text.newToOld[i + 1] == null) && (text.oldToNew[j + 1] == null) ) {

// connect if the following words are the same
        if (text.newWords[i + 1] == text.oldWords[j + 1]) {
          text.newToOld[i + 1] = j + 1;
          text.oldToNew[j + 1] = i + 1;
        }
      }
    }
  }

//
// pass 5: connect adjacent identical words upwards
//
  for (var i = newEnd - 1; i > newStart; i --) {

// find already connected pairs
    if (text.newToOld[i] != null) {
      var j = text.newToOld[i];

// check if the preceeding words are not yet connected
      if ( (text.newToOld[i - 1] == null) && (text.oldToNew[j - 1] == null) ) {

// connect if the preceeding words are the same
        if ( text.newWords[i - 1] == text.oldWords[j - 1] ) {
          text.newToOld[i - 1] = j - 1;
          text.oldToNew[j - 1] = i - 1;
        }
      }
    }
  }

//
// "pass" 6: recursively diff still unresolved regions downwards
//
  if (wDiffRecursiveDiff == true) {
    var i = newStart;
    var j = oldStart;
    while (i < newEnd) {
      if (text.newToOld[i - 1] != null) {
        j = text.newToOld[i - 1] + 1;
      }

// check for the start of an unresolved sequence
      if ( (text.newToOld[i] == null) && (text.oldToNew[j] == null) ) {

// determine the ends of the sequences
        var iStart = i;
        var iEnd = i;
        while ( (text.newToOld[iEnd] == null) && (iEnd < newEnd) ) {
          iEnd ++;
        }
        var iLength = iEnd - iStart;

        var jStart = j;
        var jEnd = j;
        while ( (text.oldToNew[jEnd] == null) && (jEnd < oldEnd) ) {
          jEnd ++;
        }
        var jLength = jEnd - jStart;

// recursively diff the unresolved sequence
        if ( (iLength > 0) && (jLength > 0) ) {
          if ( (iLength > 1) || (jLength > 1) ) {
            if ( (iStart != newStart) || (iEnd != newEnd) || (jStart != oldStart) || (jEnd != oldEnd) ) {
              WDiffText(text, iStart, iEnd, jStart, jEnd, recursionLevel + 1);
            }
          }
        }
        i = iEnd;
      }
      else {
        i ++;
      }
    }
  }

//
// "pass" 7: recursively diff still unresolved regions upwards
//
  if (wDiffRecursiveDiff == true) {
    var i = newEnd - 1;
    var j = oldEnd - 1;
    while (i >= newStart) {
      if (text.newToOld[i + 1] != null) {
        j = text.newToOld[i + 1] - 1;
      }

// check for the start of an unresolved sequence
      if ( (text.newToOld[i] == null) && (text.oldToNew[j] == null) ) {

// determine the ends of the sequences
        var iStart = i;
        var iEnd = i + 1;
        while ( (text.newToOld[iStart - 1] == null) && (iStart >= newStart) ) {
          iStart --;
        }
        if (iStart < 0) {
          iStart = 0;
        }
        var iLength = iEnd - iStart;

        var jStart = j;
        var jEnd = j + 1;
        while ( (text.oldToNew[jStart - 1] == null) && (jStart >= oldStart) ) {
          jStart --;
        }
        if (jStart < 0) {
          jStart = 0;
        }
        var jLength = jEnd - jStart;

// recursively diff the unresolved sequence
        if ( (iLength > 0) && (jLength > 0) ) {
          if ( (iLength > 1) || (jLength > 1) ) {
            if ( (iStart != newStart) || (iEnd != newEnd) || (jStart != oldStart) || (jEnd != oldEnd) ) {
              WDiffText(text, iStart, iEnd, jStart, jEnd, recursionLevel + 1);
            }
          }
        }
        i = iStart - 1;
      }
      else {
        i --;
      }
    }
  }
  return;
};


// WDiffToHtml: process diff data into formatted html text
// input: text.newWords and text.oldWords, arrays containing the texts in arrays of words
//   text.newToOld and text.oldToNew, arrays pointing to corresponding words
//   block data structure
// returns: outText, a html string

window.WDiffToHtml = function(text, block) {

  var outText = text.message;

  var blockNumber = 0;
  var i = 0;
  var j = 0;
  var movedAsInsertion;

// cycle through the new text
  do {
    var movedIndex = [];
    var movedBlock = [];
    var movedLeft = [];
    var blockText = '';
    var identText = '';
    var delText = '';
    var insText = '';
    var identStart = '';

// check if a block ends here and finish previous block
    if (movedAsInsertion != null) {
      if (movedAsInsertion == false) {
        identStart += wDiffHtmlBlockEnd;
      }
      else {
        identStart += wDiffHtmlInsertEnd;
      }
      movedAsInsertion = null;
    }

// detect block boundary
    if ( (text.newToOld[i] != j) || (blockNumber == 0 ) ) {
      if ( ( (text.newToOld[i] != null) || (i >= text.newWords.length) ) && ( (text.oldToNew[j] != null) || (j >= text.oldWords.length) ) ) {

// block moved right
        var moved = block.newRight[blockNumber];
        if (moved > 0) {
          var index = block.newRightIndex[blockNumber];
          movedIndex.push(index);
          movedBlock.push(moved);
          movedLeft.push(false);
        }

// block moved left
        moved = block.newLeft[blockNumber];
        if (moved > 0) {
          var index = block.newLeftIndex[blockNumber];
          movedIndex.push(index);
          movedBlock.push(moved);
          movedLeft.push(true);
        }

// check if a block starts here
        moved = block.newBlock[blockNumber];
        if (moved > 0) {

// mark block as inserted text
          if (block.newWords[blockNumber] < wDiffBlockMinLength) {
            identStart += wDiffHtmlInsertStart;
            movedAsInsertion = true;
          }

// mark block by color
          else {
            if (moved > wDiffStyleBlock.length) {
              moved = wDiffStyleBlock.length;
            }
            identStart += WDiffHtmlCustomize(wDiffHtmlBlockStart, moved - 1);
            movedAsInsertion = false;
          }
        }

        if (i >= text.newWords.length) {
          i ++;
        }
        else {
          j = text.newToOld[i];
          blockNumber ++;
        }
      }
    }

// get the correct order if moved to the left as well as to the right from here
    if (movedIndex.length == 2) {
      if (movedIndex[0] > movedIndex[1]) {
        movedIndex.reverse();
        movedBlock.reverse();
        movedLeft.reverse();
      }
    }

// handle left and right block moves from this position
    for (var m = 0; m < movedIndex.length; m ++) {

// insert the block as deleted text
      if (block.newWords[ movedIndex[m] ] < wDiffBlockMinLength) {
        var movedStart = block.newStart[ movedIndex[m] ];
        var movedLength = block.newLength[ movedIndex[m] ];
        var str = '';
        for (var n = movedStart; n < movedStart + movedLength; n ++) {
          str += text.newWords[n];
        }
        str = WDiffEscape(str);
        str = str.replace(/\n/g, '<span class="wDiffParagraph"></span><br>');
        blockText += wDiffHtmlDeleteStart + str + wDiffHtmlDeleteEnd;
      }

// add a placeholder / move direction indicator
      else {
        if (movedBlock[m] > wDiffStyleBlock.length) {
          movedBlock[m] = wDiffStyleBlock.length;
        }
        if (movedLeft[m]) {
          blockText += WDiffHtmlCustomize(wDiffHtmlMovedLeft, movedBlock[m] - 1);
        }
        else {
          blockText += WDiffHtmlCustomize(wDiffHtmlMovedRight, movedBlock[m] - 1);
        }
      }
    }

// collect consecutive identical text
    while ( (i < text.newWords.length) && (j < text.oldWords.length) ) {
      if ( (text.newToOld[i] == null) || (text.oldToNew[j] == null) ) {
        break;
      }
      if (text.newToOld[i] != j) {
        break;
      }
      identText += text.newWords[i];
      i ++;
      j ++;
    }

// collect consecutive deletions
    while ( (text.oldToNew[j] == null) && (j < text.oldWords.length) ) {
      delText += text.oldWords[j];
      j ++;
    }

// collect consecutive inserts
    while ( (text.newToOld[i] == null) && (i < text.newWords.length) ) {
      insText += text.newWords[i];
      i ++;
    }

// remove leading and trailing similarities between delText and ins from highlighting
    var preText = '';
    var postText = '';
    if (wDiffWordDiff) {
      if ( (delText != '') && (insText != '') ) {

// remove leading similarities
        while ( delText.charAt(0) == insText.charAt(0) && (delText != '') && (insText != '') ) {
          preText = preText + delText.charAt(0);
          delText = delText.substr(1);
          insText = insText.substr(1);
        }

// remove trailing similarities
        while ( delText.charAt(delText.length - 1) == insText.charAt(insText.length - 1) && (delText != '') && (insText != '') ) {
          postText = delText.charAt(delText.length - 1) + postText;
          delText = delText.substr(0, delText.length - 1);
          insText = insText.substr(0, insText.length - 1);
        }
      }
    }

// output the identical text, deletions and inserts

// moved from here indicator
    if (blockText != '') {
      outText += blockText;
    }

// identical text
    if (identText != '') {
      outText += identStart + WDiffEscape(identText);
    }
    outText += preText;

// deleted text
    if (delText != '') {
      delText = wDiffHtmlDeleteStart + WDiffEscape(delText) + wDiffHtmlDeleteEnd;
      delText = delText.replace(/\n/g, '<span class="wDiffParagraph"></span><br>');
      outText += delText;
    }

// inserted text
    if (insText != '') {
      insText = wDiffHtmlInsertStart + WDiffEscape(insText) + wDiffHtmlInsertEnd;
      insText = insText.replace(/\n/g, '<span class="wDiffParagraph"></span><br>');
      outText += insText;
    }
    outText += postText;
  } while (i <= text.newWords.length);

  outText += '\n';
  outText = WDiffHtmlFormat(outText);

  return(outText);
};


// WDiffEscape: replaces html-sensitive characters in output text with character entities

window.WDiffEscape = function(text) {

  text = text.replace(/&/g, '&amp;');
  text = text.replace(/</g, '&lt;');
  text = text.replace(/>/g, '&gt;');
  text = text.replace(/"/g, '&quot;');

  return(text);
};


// HtmlCustomize: customize indicator html: replace {number} with the block number, {block} with the block style

window.WDiffHtmlCustomize = function(text, block) {

  text = text.replace(/\{number\}/, block);
  text = text.replace(/\{block\}/, wDiffStyleBlock[block]);

  return(text);
};


// HtmlFormat: replaces newlines and multiple spaces in text with html code

window.WDiffHtmlFormat = function(text) {

  text = text.replace(/ {2}/g, ' &nbsp;');
  text = text.replace(/\n/g, '<br>');

  return(text);
};


// WDiffDetectBlocks: detect block borders and moved blocks
// input: text object, block object

window.WDiffDetectBlocks = function(text, block) {

  block.oldStart  = [];
  block.oldToNew  = [];
  block.oldLength = [];
  block.oldWords  = [];
  block.newStart  = [];
  block.newLength = [];
  block.newWords  = [];
  block.newNumber = [];
  block.newBlock  = [];
  block.newLeft   = [];
  block.newRight  = [];
  block.newLeftIndex  = [];
  block.newRightIndex = [];

  var blockNumber = 0;
  var wordCounter = 0;
  var realWordCounter = 0;

// get old text block order
  if (wDiffShowBlockMoves) {
    var j = 0;
    var i = 0;
    do {

// detect block boundaries on old text
      if ( (text.oldToNew[j] != i) || (blockNumber == 0 ) ) {
        if ( ( (text.oldToNew[j] != null) || (j >= text.oldWords.length) ) && ( (text.newToOld[i] != null) || (i >= text.newWords.length) ) ) {
          if (blockNumber > 0) {
            block.oldLength[blockNumber - 1] = wordCounter;
            block.oldWords[blockNumber - 1] = realWordCounter;
            wordCounter = 0;
            realWordCounter = 0;
          }

          if (j >= text.oldWords.length) {
            j ++;
          }
          else {
            i = text.oldToNew[j];
            block.oldStart[blockNumber] = j;
            block.oldToNew[blockNumber] = text.oldToNew[j];
            blockNumber ++;
          }
        }
      }

// jump over identical pairs
      while ( (i < text.newWords.length) && (j < text.oldWords.length) ) {
        if ( (text.newToOld[i] == null) || (text.oldToNew[j] == null) ) {
          break;
        }
        if (text.oldToNew[j] != i) {
          break;
        }
        i ++;
        j ++;
        wordCounter ++;
        if ( /\w/.test( text.newWords[i] ) ) {
          realWordCounter ++;
        }
      }

// jump over consecutive deletions
      while ( (text.oldToNew[j] == null) && (j < text.oldWords.length) ) {
        j ++;
      }

// jump over consecutive inserts
      while ( (text.newToOld[i] == null) && (i < text.newWords.length) ) {
        i ++;
      }
    } while (j <= text.oldWords.length);

// get the block order in the new text
    var lastMin;
    var currMinIndex;
    lastMin = null;

// sort the data by increasing start numbers into new text block info
    for (var i = 0; i < blockNumber; i ++) {
      currMin = null;
      for (var j = 0; j < blockNumber; j ++) {
        curr = block.oldToNew[j];
        if ( (curr > lastMin) || (lastMin == null) ) {
          if ( (curr < currMin) || (currMin == null) ) {
            currMin = curr;
            currMinIndex = j;
          }
        }
      }
      block.newStart[i] = block.oldToNew[currMinIndex];
      block.newLength[i] = block.oldLength[currMinIndex];
      block.newWords[i] = block.oldWords[currMinIndex];
      block.newNumber[i] = currMinIndex;
      lastMin = currMin;
    }

// detect not moved blocks
    for (var i = 0; i < blockNumber; i ++) {
      if (block.newBlock[i] == null) {
        if (block.newNumber[i] == i) {
          block.newBlock[i] = 0;
        }
      }
    }

// detect switches of neighbouring blocks
    for (var i = 0; i < blockNumber - 1; i ++) {
      if ( (block.newBlock[i] == null) && (block.newBlock[i + 1] == null) ) {
        if (block.newNumber[i] - block.newNumber[i + 1] == 1) {
          if ( (block.newNumber[i + 1] - block.newNumber[i + 2] != 1) || (i + 2 >= blockNumber) ) {

// the shorter one is declared the moved one
            if (block.newLength[i] < block.newLength[i + 1]) {
              block.newBlock[i] = 1;
              block.newBlock[i + 1] = 0;
            }
            else {
              block.newBlock[i] = 0;
              block.newBlock[i + 1] = 1;
            }
          }
        }
      }
    }

// mark all others as moved and number the moved blocks
    j = 1;
    for (var i = 0; i < blockNumber; i ++) {
      if ( (block.newBlock[i] == null) || (block.newBlock[i] == 1) ) {
        block.newBlock[i] = j++;
      }
    }

// check if a block has been moved from this block border
    for (var i = 0; i < blockNumber; i ++) {
      for (var j = 0; j < blockNumber; j ++) {

        if (block.newNumber[j] == i) {
          if (block.newBlock[j] > 0) {

// block moved right
            if (block.newNumber[j] < j) {
              block.newRight[i] = block.newBlock[j];
              block.newRightIndex[i] = j;
            }

// block moved left
            else {
              block.newLeft[i + 1] = block.newBlock[j];
              block.newLeftIndex[i + 1] = j;
            }
          }
        }
      }
    }
  }
  return;
};


// WDiffShortenOutput: remove unchanged parts from final output
// input: the output of WDiffString
// returns: the text with removed unchanged passages indicated by (...)

window.WDiffShortenOutput = function(diffText) {

// html <br/> to newlines
  diffText = diffText.replace(/<br[^>]*>/g, '\n');

// scan for diff html tags
  var regExpDiff = /<\w+ class="(\w+)"[^>]*>(.|\n)*?<!--\1-->/g;
  var tagStart = [];
  var tagEnd = [];
  var i = 0;
  var found;
  while ( (found = regExpDiff.exec(diffText)) != null ) {

// combine consecutive diff tags
    if ( (i > 0) && (tagEnd[i - 1] == found.index) ) {
      tagEnd[i - 1] = found.index + found[0].length;
    }
    else {
      tagStart[i] = found.index;
      tagEnd[i] = found.index + found[0].length;
      i ++;
    }
  }

// no diff tags detected
  if (tagStart.length == 0) {
    return(wDiffNoChange);
  }

// define regexps
  var regExpHeading = /\n=+.+?=+ *\n|\n\{\||\n\|\}/g;
  var regExpParagraph = /\n\n+/g;
  var regExpLine = /\n+/g;
  var regExpBlank = /(<[^>]+>)*\s+/g;

// determine fragment border positions around diff tags
  var rangeStart = [];
  var rangeEnd = [];
  var rangeStartType = [];
  var rangeEndType = [];
  for (var i = 0; i < tagStart.length; i ++) {
    var found;

// find last heading before diff tag
    var lastPos = tagStart[i] - wDiffHeadingBefore;
    if (lastPos < 0) {
      lastPos = 0;
    }
    regExpHeading.lastIndex = lastPos;
    while ( (found = regExpHeading.exec(diffText)) != null ) {
      if (found.index > tagStart[i]) {
        break;
      }
      rangeStart[i] = found.index;
      rangeStartType[i] = 'heading';
    }

// find last paragraph before diff tag
    if (rangeStart[i] == null) {
      lastPos = tagStart[i] - wDiffParagraphBefore;
      if (lastPos < 0) {
        lastPos = 0;
      }
      regExpParagraph.lastIndex = lastPos;
      while ( (found = regExpParagraph.exec(diffText)) != null ) {
        if (found.index > tagStart[i]) {
          break;
        }
        rangeStart[i] = found.index;
        rangeStartType[i] = 'paragraph';
      }
    }

// find line break before diff tag
    if (rangeStart[i] == null) {
      lastPos = tagStart[i] - wDiffLineBeforeMax;
      if (lastPos < 0) {
        lastPos = 0;
      }
      regExpLine.lastIndex = lastPos;
      while ( (found = regExpLine.exec(diffText)) != null ) {
        if (found.index > tagStart[i] - wDiffLineBeforeMin) {
          break;
        }
        rangeStart[i] = found.index;
        rangeStartType[i] = 'line';
      }
    }

// find blank before diff tag
    if (rangeStart[i] == null) {
      lastPos = tagStart[i] - wDiffBlankBeforeMax;
      if (lastPos < 0) {
        lastPos = 0;
      }
      regExpBlank.lastIndex = lastPos;
      while ( (found = regExpBlank.exec(diffText)) != null ) {
        if (found.index > tagStart[i] - wDiffBlankBeforeMin) {
          break;
        }
        rangeStart[i] = found.index;
        rangeStartType[i] = 'blank';
      }
    }

// fixed number of chars before diff tag
    if (rangeStart[i] == null) {
      rangeStart[i] = tagStart[i] - wDiffCharsBefore;
      rangeStartType[i] = 'chars';
      if (rangeStart[i] < 0) {
        rangeStart[i] = 0;
      }
    }

// find first heading after diff tag
    regExpHeading.lastIndex = tagEnd[i];
    if ( (found = regExpHeading.exec(diffText)) != null ) {
      if (found.index < tagEnd[i] + wDiffHeadingAfter) {
        rangeEnd[i] = found.index + found[0].length;
        rangeEndType[i] = 'heading';
      }
    }

// find first paragraph after diff tag
    if (rangeEnd[i] == null) {
      regExpParagraph.lastIndex = tagEnd[i];
      if ( (found = regExpParagraph.exec(diffText)) != null ) {
        if (found.index < tagEnd[i] + wDiffParagraphAfter) {
          rangeEnd[i] = found.index;
          rangeEndType[i] = 'paragraph';
        }
      }
    }

// find first line break after diff tag
    if (rangeEnd[i] == null) {
      regExpLine.lastIndex = tagEnd[i] + wDiffLineAfterMin;
      if ( (found = regExpLine.exec(diffText)) != null ) {
        if (found.index < tagEnd[i] + wDiffLineAfterMax) {
          rangeEnd[i] = found.index;
          rangeEndType[i] = 'break';
        }
      }
    }

// find blank after diff tag
    if (rangeEnd[i] == null) {
      regExpBlank.lastIndex = tagEnd[i] + wDiffBlankAfterMin;
      if ( (found = regExpBlank.exec(diffText)) != null ) {
        if (found.index < tagEnd[i] + wDiffBlankAfterMax) {
          rangeEnd[i] = found.index;
          rangeEndType[i] = 'blank';
        }
      }
    }

// fixed number of chars after diff tag
    if (rangeEnd[i] == null) {
      rangeEnd[i] = tagEnd[i] + wDiffCharsAfter;
      if (rangeEnd[i] > diffText.length) {
        rangeEnd[i] = diffText.length;
        rangeEndType[i] = 'chars';
      }
    }
  }

// remove overlaps, join close fragments
  var fragmentStart = [];
  var fragmentEnd = [];
  var fragmentStartType = [];
  var fragmentEndType = [];
  fragmentStart[0] = rangeStart[0];
  fragmentEnd[0] = rangeEnd[0];
  fragmentStartType[0] = rangeStartType[0];
  fragmentEndType[0] = rangeEndType[0];
  var j = 1;
  for (var i = 1; i < rangeStart.length; i ++) {
    if (rangeStart[i] > fragmentEnd[j - 1] + wDiffFragmentJoin) {
      fragmentStart[j] = rangeStart[i];
      fragmentEnd[j] = rangeEnd[i];
      fragmentStartType[j] = rangeStartType[i];
      fragmentEndType[j] = rangeEndType[i];
      j ++;
    }
    else {
      fragmentEnd[j - 1] = rangeEnd[i];
      fragmentEndType[j - 1] = rangeEndType[i];
    }
  }

// assemble the fragments
  var outText = '';
  for (var i = 0; i < fragmentStart.length; i ++) {

// get text fragment
    var fragment = diffText.substring(fragmentStart[i], fragmentEnd[i]);
    var fragment = fragment.replace(/^\n+|\n+$/g, '');

// add inline marks for omitted chars and words
    if (fragmentStart[i] > 0) {
      if (fragmentStartType[i] == 'chars') {
        fragment = wDiffOmittedChars + fragment;
      }
      else if (fragmentStartType[i] == 'blank') {
        fragment = wDiffOmittedChars + ' ' + fragment;
      }
    }
    if (fragmentEnd[i] < diffText.length) {
      if (fragmentStartType[i] == 'chars') {
        fragment = fragment + wDiffOmittedChars;
      }
      else if (fragmentStartType[i] == 'blank') {
        fragment = fragment + ' ' + wDiffOmittedChars;
      }
    }

// add omitted line separator
    if (fragmentStart[i] > 0) {
      outText += wDiffOmittedLines;
    }

// encapsulate span errors
    outText += '<div>' + fragment + '</div>';
  }

// add trailing omitted line separator
  if (fragmentEnd[i - 1] < diffText.length) {
    outText = outText + wDiffOmittedLines;
  }

// remove leading and trailing empty lines
  outText = outText.replace(/^(<div>)\n+|\n+(<\/div>)$/g, '$1$2');

// convert to html linebreaks
  outText = outText.replace(/\n/g, '<br />');

  return(outText);
};

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
