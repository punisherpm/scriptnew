<?php
/**
 * Simple HTML DOM Parser for PHP 5.3
 * Source: http://sourceforge.net/projects/simplehtmldom/
 * UTF-8 compatible, no composer required
 */

if (defined('SIMPLE_HTML_DOM_VERSION')) return;
define('SIMPLE_HTML_DOM_VERSION', '1.5');

define('HDOM_TYPE_ELEMENT', 1);
define('HDOM_TYPE_COMMENT', 2);
define('HDOM_TYPE_TEXT', 3);
define('HDOM_TYPE_ENDTAG', 4);
define('HDOM_TYPE_ROOT', 5);
define('HDOM_TYPE_UNKNOWN', 6);
define('HDOM_QUOTE_DOUBLE', 0);
define('HDOM_QUOTE_SINGLE', 1);
define('HDOM_QUOTE_NO', 3);
define('HDOM_INFO_BEGIN', 0);
define('HDOM_INFO_END', 1);
define('HDOM_INFO_QUOTE', 2);
define('HDOM_INFO_SPACE', 3);
define('HDOM_INFO_TEXT', 4);
define('HDOM_INFO_INNER', 5);
define('HDOM_INFO_OUTER', 6);
define('HDOM_INFO_ENDSPACE', 7);

class simple_html_dom_node {
    public $nodetype = HDOM_TYPE_TEXT;
    public $tag = 'text';
    public $attr = array();
    public $children = array();
    public $nodes = array();
    public $parent = null;
    public $innertext = '';
    public $outertext = '';
    public $plaintext = '';

    public function __construct($dom) {
        $dom->nodes[] = $this;
    }

    public function __toString() {
        return $this->outertext();
    }

    public function outertext() {
        if ($this->tag == 'text') return $this->innertext;
        $ret = '<' . $this->tag;
        foreach ($this->attr as $k => $v) {
            $ret .= ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
        }
        if (in_array($this->tag, array('br', 'img', 'input', 'meta', 'link'))) {
            return $ret . ' />';
        }
        return $ret . '>' . $this->innertext . '</' . $this->tag . '>';
    }

    public function find($selector, $idx = null) {
        $ret = array();
        $search = strtolower($selector);
        foreach ($this->nodes as $n) {
            if (isset($n->tag) && strtolower($n->tag) == $search) {
                $ret[] = $n;
            }
            $sub = $n->find($selector);
            if (is_array($sub)) $ret = array_merge($ret, $sub);
        }
        if (is_null($idx)) return $ret;
        return isset($ret[$idx]) ? $ret[$idx] : null;
    }
}

class simple_html_dom {
    public $root;
    public $nodes = array();

    public function __construct() {
        $this->root = new simple_html_dom_node($this);
        $this->root->tag = 'root';
        $this->root->nodetype = HDOM_TYPE_ROOT;
    }

    public function load($str) {
        $str = trim($str);
        if ($str == '') return false;

        // basic regex parser for PHP 5.3 compatibility
        $pattern = '/<([a-z0-9]+)([^>]*)>(.*?)<\/\1>/is';
        preg_match_all($pattern, $str, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $node = new simple_html_dom_node($this);
            $node->tag = strtolower($m[1]);
            $node->innertext = $m[3];
            $this->root->nodes[] = $node;
        }
    }

    public function find($selector, $idx = null) {
        return $this->root->find($selector, $idx);
    }
}

function file_get_html($url) {
    $dom = new simple_html_dom();
    $str = @file_get_contents($url);
    if ($str === false) return false;
    $dom->load($str);
    return $dom;
}

function str_get_html($str) {
    $dom = new simple_html_dom();
    $dom->load($str);
    return $dom;
}
?>
