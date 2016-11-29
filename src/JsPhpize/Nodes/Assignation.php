<?php

namespace JsPhpize\Nodes;

use JsPhpize\Parser\Exception;

class Assignation extends Value
{
    /**
     * @var Assignable
     */
    protected $leftHand;

    /**
     * @var Value
     */
    protected $rightHand;

    /**
     * @var string
     */
    protected $operator;

    public function __construct($operator, Assignable $leftHand, Node $rightHand)
    {
        if (!$leftHand->isAssignable()) {
            throw new Exception($leftHand->getNonAssignableReason(), 9);
        }

        $this->operator = $operator;
        $this->leftHand = $leftHand;
        $this->rightHand = $rightHand;
    }
}
