<?php

/**
 * This is a simple sql tokenizer / parser.
 * 
 * It does NOT support multiline comments at this time.
 * 
 * See the included example.php for usage.
 *
 * THIS CODE IS A PROTOTYPE/BETA
 *
 * @author Justin Carlson <justin.carlson@gmail.com>
 * @license LGPL 3
 * @version 0.0.4
 */
namespace app\inc;
class SqlParser {

    var $handle = null;
    
    // statements 
    public static $querysections = array('alter','create','drop', 
                                         'select', 'delete', 'insert', 
                                         'update', 'from', 'where', 
                                         'limit', 'order');
    
    // operators
    public static $operators = array('=', '<>', '<', '<=', '>', '>=', 
                                     'like', 'clike', 'slike', 'not', 
                                     'is', 'in', 'between');
    
    // types
    public static $types = array('character', 'char', 'varchar', 'nchar', 
                                 'bit', 'numeric', 'decimal', 'dec', 
                                 'integer', 'int', 'smallint', 'float', 
                                 'real', 'double', 'date', 'datetime', 
                                 'time', 'timestamp', 'interval', 
                                 'bool', 'boolean', 'set', 'enum', 'text');
    
    // conjuctions
    public static $conjuctions = array('by', 'as', 'on', 'into', 
                                       'from', 'where', 'with');
    
    // basic functions
    public static $funcitons = array('avg', 'count', 'max', 'min', 
                                     'sum', 'nextval', 'currval', 'concat',
                                     );
    
    // reserved keywords
    public static $reserved = array('absolute', 'action', 'add', 'all', 
                                    'allocate', 'and', 'any', 'are', 'asc', 
                                    'ascending', 'assertion', 'at', 
                                    'authorization', 'begin', 'bit_length', 
                                    'both', 'cascade', 'cascaded', 'case', 
                                    'cast', 'catalog', 'char_length', 
                                    'character_length', 'check', 'close', 
                                    'coalesce', 'collate', 'collation', 
                                    'column', 'commit', 'connect', 'connection',
                                    'constraint', 'constraints', 'continue', 
                                    'convert', 'corresponding', 'cross', 
                                    'current', 'current_date', 'current_time', 
                                    'current_timestamp', 'current_user', 
                                    'cursor', 'day', 'deallocate', 'declare', 
                                    'default', 'deferrable', 'deferred', 'desc',
                                    'descending', 'describe', 'descriptor', 
                                    'diagnostics', 'disconnect', 'distinct', 
                                    'domain', 'else', 'end', 'end-exec', 
                                    'escape', 'except', 'exception', 'exec', 
                                    'execute', 'exists', 'external', 'extract',
                                    'false', 'fetch', 'first', 'for', 'foreign',
                                    'found', 'full', 'get', 'global', 'go', 
                                    'goto', 'grant', 'group', 'having', 'hour',
                                    'identity', 'immediate', 'indicator', 
                                    'initially', 'inner', 'input', 
                                    'insensitive', 'intersect', 'isolation', 
                                    'join', 'key', 'language', 'last', 
                                    'leading', 'left', 'level', 'limit', 
                                    'local', 'lower', 'match', 'minute', 
                                    'module', 'month', 'names', 'national', 
                                    'natural', 'next', 'no', 'null', 'nullif', 
                                    'octet_length', 'of', 'only', 'open', 
                                    'option', 'or', 'order', 'outer', 'output',
                                    'overlaps', 'pad', 'partial', 'position', 
                                    'precision', 'prepare', 'preserve', 
                                    'primary', 'prior', 'privileges', 
                                    'procedure', 'public', 'read', 'references',
                                    'relative', 'restrict', 'revoke', 'right',
                                    'rollback', 'rows', 'schema', 'scroll', 
                                    'second', 'section', 'session', 
                                    'session_user', 'size', 'some', 'space',
                                    'sql', 'sqlcode', 'sqlerror', 'sqlstate', 
                                    'substring', 'system_user', 'table', 
                                    'temporary', 'then', 'timezone_hour', 
                                    'timezone_minute', 'to', 'trailing', 
                                    'transaction', 'translate', 'translation', 
                                    'trim', 'true', 'union', 'unique', 
                                    'unknown', 'upper', 'usage', 'user', 
                                    'using', 'value', 'values', 'varying', 
                                    'view', 'when', 'whenever', 'work', 'write',
                                    'year', 'zone', 'eoc');
    
    // open parens, tokens, and brackets
    public static $startparens = array('{', '(');
    public static $endparens = array('}', ')');
    public static $tokens = array(',', ' ');
    private $query = '';

    // constructor (placeholder only)
    public function __construct() {
        
    }

    /**
     * Simple SQL Tokenizer
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license GPL
     * @param string $sqlQuery
     * @return token array
     */
    public static function Tokenize($sqlQuery, $cleanWhitespace = true) {

        /**
         * Strip extra whitespace from the query
         */
        if ($cleanWhitespace === true) {
            $sqlQuery = ltrim(preg_replace('/[\\s]{2,}/', ' ', $sqlQuery));
        }

        /**
         * Regular expression parsing.
         * Inspired/Based on the Perl SQL::Tokenizer by Igor Sutton Lopes
         */
        
        // begin group
        $regex = '(';
        
        // inline comments
        $regex .= '(?:--|\\#)[\\ \\t\\S]*';
        
        // logical operators
        $regex .= '|(?:<>|<=>|>=|<=|==|=|!=|!|<<|>>|<|>|\\|\\||\\||&&|&|-';
        $regex .= '|\\+|\\*(?!\/)|\/(?!\\*)|\\%|~|\\^|\\?)';
        
        // empty quotes
        $regex .= '|[\\[\\]\\(\\),;`]|\\\'\\\'(?!\\\')|\\"\\"(?!\\"")';
        
        // string quotes
        $regex .= '|".*?(?:(?:""){1,}"';
        $regex .= '|(?<!["\\\\])"(?!")|\\\\"{2})';
        $regex .= '|\'.*?(?:(?:\'\'){1,}\'';
        $regex .= '|(?<![\'\\\\])\'(?!\')';
        $regex .= '|\\\\\'{2})';
        
        // c comments
        $regex .= '|\/\\*[\\ \\t\\n\\S]*?\\*\/';
        
        // wordds, column strings, params
        $regex .= '|(?:[\\w:@]+(?:\\.(?:\\w+|\\*)?)*)';
        $regex .= '|[\t\ ]+';
        
        // period and whitespace
        $regex .= '|[\.]'; 
        $regex .= '|[\s]'; 

        $regex .= ')'; # end group
        
        // perform a global match
        preg_match_all('/' . $regex . '/smx', $sqlQuery, $result);

        // return tokens
        return $result[0];
    }

    /**
     * Simple SQL Parser
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @param string $sqlQuery
     * @param bool optional $cleanup
     * @return SqlParser Object
     */
    public static function ParseString($sqlQuery, $cleanWhitespace = true) {

        // instantiate if called statically
        if (!isset($this)) {
            $handle = new SqlParser();
        } else {
            $handle = $this;
        }

        // copy and tokenize the query
        $tokens = self::Tokenize($sqlQuery, $cleanWhitespace);
        $tokenCount = count($tokens);
        $queryParts = array();
        if (isset($tokens[0])===true) {
            $section = $tokens[0];
        }

        // parse the tokens
        for ($t = 0; $t < $tokenCount; $t++) {

            // if is paren
            if (in_array($tokens[$t], self::$startparens)) {

                // read until closed
                $sub = $handle->readsub($tokens, $t);
                $handle->query[$section].= $sub;
                
            } else {

                if (in_array(strtolower($tokens[$t]), self::$querysections) && !isset($handle->query[$tokens[$t]])) {
                    $section = strtolower($tokens[$t]);
                }

                // rebuild the query in sections
                if (!isset($handle->query[$section]))
                    $handle->query[$section] = '';
                $handle->query[$section] .= $tokens[$t];
            }
        }

        return $handle;
    }

    /**
     * Parses a sub-section of a query
     *
     * @param array $tokens
     * @param int $position
     * @return string section
     */
    private function readsub($tokens, &$position) {

        $sub = $tokens[$position];
        $tokenCount = count($tokens);
        $position++;
        while (!in_array($tokens[$position], self::$endparens) && $position < $tokenCount) {

            if (in_array($tokens[$position], self::$startparens)) {
                $sub.= $this->readsub($tokens, $position);
                $subs++;
            } else {
                $sub.= $tokens[$position];
            }
            $position++;
        }
        $sub.= $tokens[$position];
        return $sub;
    }

    /**
     * Returns manipulated sql to get the number of rows in the query.
     * Can be used for simple pagination, for example.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function getCountQuery($optName = 'count') {

        // create temp copy of query
        $temp = $this->query;
        
        // create count() version of select and unset any limit statement
        $temp['select'] = 'select count(*) as `'.$optName.'` ';
        if (isset($temp['limit'])) {
            unset($temp['limit']);
        }
        
        return implode(null, $temp);
    }

    /**
     * Returns manipulated sql to get the unlimited number of rows in the query.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function getLimitedCountQuery() {

        $this->query['select'] = 'select count(*) as `count` ';
        return implode('', $this->query);
    }

    /**
     * Returns the select section of the query.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function getSelectStatement() {

        return $this->query['select'];
    }

    /**
     * Returns the from section of the query.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function getFromStatement() {

        return $this->query['from'];
    }

    /**
     * Returns the where section of the query.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function getWhereStatement() {

        return $this->query['where'];
    }

    /**
     * Returns the limit section of the query.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function getLimitStatement() {

        return $this->query['limit'];
    }

    /**
     * Returns the specified section of the query.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function get($which) {

        if (!isset($this->query[$which]))
            return false;
        return $this->query[$which];
    }

    /**
     * Returns the sections of the query.
     *
     * @author Justin Carlson <justin.carlson@gmail.com>
     * @license LGPL 3
     * @return string sql
     */
    public function getArray() {

        return $this->query;
    }

}

/**
 *  Note: The closing tag of a PHP block at the end of a file is optional, 
 *  and in some cases omitting it is helpful when using include() or require(),
 *  so unwanted whitespace will not occur at the end of files
 */
