<?php

namespace app\inc;

use InvalidArgumentException;
use RuntimeException;

class ShapeFilter
{
    // Token types
    private const T_IDENT   = 'IDENT';
    private const T_NUMBER  = 'NUMBER';
    private const T_STRING  = 'STRING';
    private const T_OP      = 'OP';
    private const T_AND     = 'AND';
    private const T_OR      = 'OR';
    private const T_IN      = 'IN';
    private const T_NOT     = 'NOT';
    private const T_LIKE    = 'LIKE';
    private const T_ILIKE   = 'ILIKE';
    private const T_LPAREN  = 'LPAREN';
    private const T_RPAREN  = 'RPAREN';
    private const T_COMMA   = 'COMMA';
    private const T_EOF     = 'EOF';

    private array $tokens = [];
    private int $pos = 0;

    // Cache of parsed ASTs keyed by normalized WHERE string
    private array $astCache = [];

    /**
     * Public entry point
     *
     * @param array  $payload Electric-style "batch" payload
     * @param string $where   SQL-like WHERE clause
     *
     * @return array filtered payload
     */
    public function filter(array $payload, string $where): array
    {
        $where = trim($where);
        if ($where === '') {
            return $payload; // no filter
        }

        // Normalize whitespace for cache key
        $normWhere = preg_replace('/\s+/', ' ', $where);

        if (isset($this->astCache[$normWhere])) {
            $ast = $this->astCache[$normWhere];
        } else {
            $this->tokens = $this->tokenize($normWhere);
            $this->pos    = 0;
            $ast          = $this->parseExpression();
            $this->astCache[$normWhere] = $ast;
        }

        if (!isset($payload['batch']) || !is_array($payload['batch'])) {
            return $payload;
        }

        foreach ($payload['batch'] as $dbName => &$tables) {
            if (!is_array($tables)) {
                continue;
            }

            foreach ($tables as $tableName => &$tableOps) {
                if (!isset($tableOps['full_data']) || !is_array($tableOps['full_data'])) {
                    // No full_data: still filter INSERT/UPDATE/DELETE based on pseudo-rows
                    $this->filterOpsWithoutFullData($tableOps, $ast);
                    continue;
                }

                $fullData = $tableOps['full_data'];
                $filteredFull = [];
                $allowedKeys = []; // map "col\0val" => true

                foreach ($fullData as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    if ($this->evaluateNode($ast, $row)) {
                        $filteredFull[] = $row;

                        // Any column/value pair can be used as a "key"
                        foreach ($row as $col => $val) {
                            $key = $col . "\0" . (string)$val;
                            $allowedKeys[$key] = true;
                        }
                    }
                }

                $tableOps['full_data'] = $filteredFull;

                // Filter INSERT / UPDATE / DELETE entries to match remaining rows
                foreach (['INSERT', 'UPDATE', 'DELETE'] as $op) {
                    if (!isset($tableOps[$op]) || !is_array($tableOps[$op])) {
                        continue;
                    }

                    $filteredOps = [];
                    foreach ($tableOps[$op] as $entry) {
                        // Expecting ["colName", "pkValue"]
                        if (!is_array($entry) || count($entry) < 2) {
                            continue;
                        }
                        [$colName, $pkVal] = $entry;
                        $key = $colName . "\0" . (string)$pkVal;
                        if (isset($allowedKeys[$key])) {
                            $filteredOps[] = $entry;
                        }
                    }

                    $tableOps[$op] = $filteredOps;
                }
            }
        }

        return $payload;
    }

    /**
     * If there's no full_data, we can still filter each op using a pseudo-row.
     */
    private function filterOpsWithoutFullData(array &$tableOps, array $ast): void
    {
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $op) {
            if (!isset($tableOps[$op]) || !is_array($tableOps[$op])) {
                continue;
            }

            $filteredOps = [];
            foreach ($tableOps[$op] as $entry) {
                if (!is_array($entry) || count($entry) < 2) {
                    continue;
                }
                [$colName, $pkVal] = $entry;
                $row = [$colName => $pkVal];

                if ($this->evaluateNode($ast, $row)) {
                    $filteredOps[] = $entry;
                }
            }

            $tableOps[$op] = $filteredOps;
        }
    }

    // ==========================
    // Tokenizer
    // ==========================

    private function tokenize(string $where): array
    {
        $len = strlen($where);
        $i   = 0;
        $tokens = [];

        while ($i < $len) {
            $ch = $where[$i];

            // Whitespace
            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            // Ident / keyword
            if (ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                $i++;
                while ($i < $len && (ctype_alnum($where[$i]) || $where[$i] === '_')) {
                    $i++;
                }
                $text  = substr($where, $start, $i - $start);
                $upper = strtoupper($text);

                switch ($upper) {
                    case 'AND':
                        $tokens[] = ['type' => self::T_AND];
                        break;
                    case 'OR':
                        $tokens[] = ['type' => self::T_OR];
                        break;
                    case 'IN':
                        $tokens[] = ['type' => self::T_IN];
                        break;
                    case 'NOT':
                        $tokens[] = ['type' => self::T_NOT];
                        break;
                    case 'LIKE':
                        $tokens[] = ['type' => self::T_LIKE];
                        break;
                    case 'ILIKE':
                        $tokens[] = ['type' => self::T_ILIKE];
                        break;
                    default:
                        $tokens[] = ['type' => self::T_IDENT, 'value' => $text];
                }
                continue;
            }

            // Number (int/float)
            if (ctype_digit($ch) || ($ch === '.' && $i + 1 < $len && ctype_digit($where[$i + 1]))) {
                $start = $i;
                $i++;
                $dotSeen = ($ch === '.');
                while ($i < $len) {
                    $c = $where[$i];
                    if ($c === '.') {
                        if ($dotSeen) {
                            break;
                        }
                        $dotSeen = true;
                        $i++;
                    } elseif (ctype_digit($c)) {
                        $i++;
                    } else {
                        break;
                    }
                }
                $numStr = substr($where, $start, $i - $start);
                $tokens[] = ['type' => self::T_NUMBER, 'value' => $numStr];
                continue;
            }

            // String literal (single or double quoted)
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $i++;
                $escaped = '';
                $closed = false;
                while ($i < $len) {
                    $c = $where[$i];
                    if ($c === '\\') {
                        if ($i + 1 < $len) {
                            $escaped .= $where[$i + 1];
                            $i += 2;
                        } else {
                            $i++;
                        }
                        continue;
                    }
                    if ($c === $quote) {
                        $closed = true;
                        $i++;
                        break;
                    }
                    $escaped .= $c;
                    $i++;
                }
                if (!$closed) {
                    throw new InvalidArgumentException("Unterminated string in WHERE clause");
                }
                $tokens[] = ['type' => self::T_STRING, 'value' => $escaped];
                continue;
            }

            // Two-char operators: >=, <=, !=, <>
            if ($i + 1 < $len) {
                $two = substr($where, $i, 2);
                if (in_array($two, ['>=', '<=', '!=', '<>'], true)) {
                    $tokens[] = ['type' => self::T_OP, 'value' => $two];
                    $i += 2;
                    continue;
                }
            }

            // One-char operators and punctuation
            switch ($ch) {
                case '=':
                case '>':
                case '<':
                    $tokens[] = ['type' => self::T_OP, 'value' => $ch];
                    $i++;
                    break;
                case '(':
                    $tokens[] = ['type' => self::T_LPAREN];
                    $i++;
                    break;
                case ')':
                    $tokens[] = ['type' => self::T_RPAREN];
                    $i++;
                    break;
                case ',':
                    $tokens[] = ['type' => self::T_COMMA];
                    $i++;
                    break;
                default:
                    throw new InvalidArgumentException("Unexpected character '$ch' in WHERE clause");
            }
        }

        $tokens[] = ['type' => self::T_EOF];
        return $tokens;
    }

    private function currentToken(): array
    {
        return $this->tokens[$this->pos] ?? ['type' => self::T_EOF];
    }

    private function eat(string $type): array
    {
        $tok = $this->currentToken();
        if ($tok['type'] !== $type) {
            throw new InvalidArgumentException(
                "Expected token $type, got {$tok['type']}"
            );
        }
        $this->pos++;
        return $tok;
    }

    private function match(string $type): bool
    {
        if ($this->currentToken()['type'] === $type) {
            $this->pos++;
            return true;
        }
        return false;
    }

    // ==========================
    // Parser: precedence + parentheses
    // Grammar:
    //   expression  := orExpr
    //   orExpr      := andExpr (OR andExpr)*
    //   andExpr     := factor (AND factor)*
    //   factor      := '(' expression ')' | condition
    //   condition   := IDENT (IN/NOT IN list | LIKE value | ILIKE value | OP value)
    // ==========================

    private function parseExpression(): array
    {
        return $this->parseOr();
    }

    private function parseOr(): array
    {
        $node = $this->parseAnd();

        while ($this->currentToken()['type'] === self::T_OR) {
            $this->eat(self::T_OR);
            $right = $this->parseAnd();
            $node = [
                'type' => 'binary',
                'op'   => 'OR',
                'left' => $node,
                'right'=> $right,
            ];
        }

        return $node;
    }

    private function parseAnd(): array
    {
        $node = $this->parseFactor();

        while ($this->currentToken()['type'] === self::T_AND) {
            $this->eat(self::T_AND);
            $right = $this->parseFactor();
            $node = [
                'type' => 'binary',
                'op'   => 'AND',
                'left' => $node,
                'right'=> $right,
            ];
        }

        return $node;
    }

    private function parseFactor(): array
    {
        if ($this->currentToken()['type'] === self::T_LPAREN) {
            $this->eat(self::T_LPAREN);
            $node = $this->parseExpression();
            $this->eat(self::T_RPAREN);
            return $node;
        }

        return $this->parseCondition();
    }

    private function parseCondition(): array
    {
        $colTok = $this->eat(self::T_IDENT);
        $column = $colTok['value'];

        $tok = $this->currentToken();

        // IN / NOT IN
        if ($tok['type'] === self::T_IN) {
            $this->eat(self::T_IN);
            $values = $this->parseValueList();
            return [
                'type'   => 'in',
                'not'    => false,
                'column' => $column,
                'values' => $values,
            ];
        }

        if ($tok['type'] === self::T_NOT) {
            $this->eat(self::T_NOT);
            $this->eat(self::T_IN);
            $values = $this->parseValueList();
            return [
                'type'   => 'in',
                'not'    => true,
                'column' => $column,
                'values' => $values,
            ];
        }

        // LIKE / ILIKE
        if ($tok['type'] === self::T_LIKE || $tok['type'] === self::T_ILIKE) {
            $this->pos++; // consume LIKE/ILIKE
            $val     = $this->parseValue();
            $pattern = (string)$val;
            $regex   = $this->likePatternToRegex(
                $pattern,
                $tok['type'] === self::T_ILIKE
            );
            return [
                'type'            => 'like',
                'column'          => $column,
                'pattern'         => $pattern,
                'regex'           => $regex,      // precompiled regex
                'caseInsensitive' => ($tok['type'] === self::T_ILIKE),
            ];
        }

        // Comparison
        $opTok = $this->eat(self::T_OP);
        $op    = $opTok['value'];
        $value = $this->parseValue();

        return [
            'type'   => 'compare',
            'column' => $column,
            'op'     => $op,
            'value'  => $value,
        ];
    }

    private function parseValueList(): array
    {
        $this->eat(self::T_LPAREN);
        $values = [];

        $values[] = $this->parseValue();
        while ($this->currentToken()['type'] === self::T_COMMA) {
            $this->eat(self::T_COMMA);
            $values[] = $this->parseValue();
        }

        $this->eat(self::T_RPAREN);
        return $values;
    }

    private function parseValue()
    {
        $tok = $this->currentToken();

        switch ($tok['type']) {
            case self::T_NUMBER:
                $this->pos++;
                $num = $tok['value'];
                return (strpos($num, '.') !== false) ? (float)$num : (int)$num;

            case self::T_STRING:
                $this->pos++;
                return $tok['value'];

            case self::T_IDENT:
                // bareword -> string
                $this->pos++;
                return $tok['value'];

            default:
                throw new InvalidArgumentException(
                    "Unexpected token {$tok['type']} where value expected"
                );
        }
    }

    // ==========================
    // Evaluator
    // ==========================

    private function evaluateNode(array $node, array $row): bool
    {
        switch ($node['type']) {
            case 'binary':
                $left  = $this->evaluateNode($node['left'], $row);
                $right = $this->evaluateNode($node['right'], $row);
                if ($node['op'] === 'AND') {
                    return $left && $right;
                }
                return $left || $right;

            case 'compare':
                return $this->evalCompare($node, $row);

            case 'in':
                return $this->evalIn($node, $row);

            case 'like':
                return $this->evalLike($node, $row);

            default:
                throw new RuntimeException("Unknown node type: {$node['type']}");
        }
    }

    private function evalCompare(array $node, array $row): bool
    {
        $col = $node['column'];
        if (!array_key_exists($col, $row)) {
            return false;
        }

        $left  = $row[$col];
        $right = $node['value'];
        $op    = $node['op'];

        if (is_numeric($left) && is_numeric($right)) {
            $left  += 0;
            $right += 0;
        }

        switch ($op) {
            case '=':
                return $left == $right;
            case '!=':
            case '<>':
                return $left != $right;
            case '>':
                return $left > $right;
            case '<':
                return $left < $right;
            case '>=':
                return $left >= $right;
            case '<=':
                return $left <= $right;
        }

        return false;
    }

    private function evalIn(array $node, array $row): bool
    {
        $col = $node['column'];
        if (!array_key_exists($col, $row)) {
            return false;
        }

        $left = $row[$col];
        $values = $node['values'];
        $in = false;

        foreach ($values as $v) {
            if (is_numeric($left) && is_numeric($v)) {
                if ($left + 0 == $v + 0) {
                    $in = true;
                    break;
                }
            } else {
                if ((string)$left === (string)$v) {
                    $in = true;
                    break;
                }
            }
        }

        return $node['not'] ? !$in : $in;
    }

    private function evalLike(array $node, array $row): bool
    {
        $col = $node['column'];
        if (!array_key_exists($col, $row)) {
            return false;
        }

        $value = (string)$row[$col];
        return (bool)preg_match($node['regex'], $value);
    }

    /**
     * Convert SQL LIKE pattern to a PHP regex.
     * - %  -> .*
     * - _  -> .
     * - \x -> escape next character literally
     */
    private function likePatternToRegex(string $pattern, bool $caseInsensitive): string
    {
        $regex = '';
        $len = strlen($pattern);

        for ($i = 0; $i < $len; $i++) {
            $ch = $pattern[$i];

            if ($ch === '\\' && $i + 1 < $len) {
                // Escape next character literally
                $i++;
                $regex .= preg_quote($pattern[$i], '/');
            } elseif ($ch === '%') {
                $regex .= '.*';
            } elseif ($ch === '_') {
                $regex .= '.';
            } else {
                $regex .= preg_quote($ch, '/');
            }
        }

        $mod = $caseInsensitive ? 'i' : '';
        return '/^' . $regex . '$/' . $mod;
    }
}




