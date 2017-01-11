<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/1/11
 * Time: 23:01
 */

include 'Expression.php';

$a = new Expression(' {a} / (1+5+(4*8)/(124+55))  + {b} + 454 * (1+8)');
echo $a->calc([
    '{a}'=>1,
    '{b}'=>1,
]);
echo "\n";
echo $a->expressionString;
echo "\n";
echo $a->getCompiledExpression();