<?php

namespace MagicTest\MagicTest\Parser\Printer;

use MagicTest\MagicTest\Grammar\Grammar;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;

class PrettyPrinter extends Standard
{
    // protected $nl = "\n";

    protected function pExpr_MethodCall(Expr\MethodCall $node): string
    {
        $call = Grammar::indent('->' . $this->pObjectProperty($node->name)
            . '(' . $this->pMaybeMultiline($node->args) . ')', 1);

        return $this->pDereferenceLhs($node->var) . $this->nl . $call;
    }
}
