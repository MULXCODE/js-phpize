<?php

namespace JsPhpize\Parser;

use JsPhpize\JsPhpize;
use JsPhpize\Nodes\Block;
use JsPhpize\Nodes\Comment;

class Visitor extends ExpressionParser
{
    protected function visitComment($token)
    {
        return new Comment($token);
    }

    public function visitLet($token)
    {
        $variable = $this->current();
        if (!$variable || $variable->type !== 'variable') {
            $this->unexpected($variable);
        }
        $varPrefix = $this->engine->getOption('varPrefix', JsPhpize::VAR_PREFIX);

        $this->getCurrentBlock()->let($variable->value, $varPrefix);

        return $this->getExpression();
    }

    public function visitKeyword($token)
    {
        if (method_exists($this, $method = 'visit' . ucfirst($token->value))) {
            return $this->$method($token);
        }

        if (($next = $this->current()) && in_array($next->type, array('(', '{'))) {
            $block = new Block($token->value);
            if ($next->type === '(') {
                $this->skip();
                $block->setParentheses($this->getExpression());
                $this->skip();
            }
            if (($next = $this->current()) && $next->type === '{') {
                $this->skip();
                $this->parseBlock($block);
            }

            return $block;
        }

        return $token->value . ' ' . $this->getExpression();
    }

    protected function visitNode($token)
    {
        $this->prepend($token);

        return $this->getExpression();
    }

    protected function visitVariable($token)
    {
        $variable = (substr($token->value, 0, 1) === '$' ? '' : '$') . $token->value;
        if (!($next = $this->current())) {
            return $variable;
        }
        if ($next->type === '(') {
            $this->skip();
            $call = $token->value . '(' . $this->getExpression() . ')';
            $this->skip();

            return $call;
        }
        if ($next->isAssignation()) {
            $this->skip();

            return
                $variable . ' ' .
                $next . ' ' .
                $this->getExpression();
        }
        $afterNext = $this->get(1);
        $variableParts = array($variable);
        while (
            $next && (
                ($parenthesis = ($next->type === '(')) ||
                ($bracket = ($next->type === '[')) || (
                    $afterNext &&
                    $next->type === '.' &&
                    $afterNext->type === 'variable'
                )
            )
        ) {
            $this->skip();
            if ($parenthesis) {
                $variableParts = array('call_user_func(call_user_func(' . $this->getHelper('dot') . ', ' . implode(', ', $variableParts) . '), ' . $this->getExpression() . ')');
            } else {
                $variableParts[] = $bracket ? $this->getExpression() : var_export($afterNext->value, true);
            }
            $this->skip();
            $next = $this->get(0);
            $afterNext = $this->get(1);
        }
        $variable = count($variableParts) === 1
            ? $variableParts[0]
            : 'call_user_func(' . $this->getHelper('dot') . ', ' . implode(', ', $variableParts) . ')';
        while ($next && $next->expectRightMember() && $next->type !== '+') {
            $this->skip();
            $variable .= ' ' . $next . ' ' . implode(' ', $this->visitToken($this->next()));
            $next = $this->current();
        }

        return $variable;
    }
}
