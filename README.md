PHP实现的表达式解析器
"# php-expression-parse" 
```php
$a = new Expression(' {a} / (1+5+(4*8)/(124+55))  + {b} + 454 * (1+8)');
echo $a->calc([
    '{a}'=>1,
    '{b}'=>1,
]);
echo "\n";
echo $a->expressionString;
echo "\n";
echo $a->getCompiledExpression();
```
```
4087.1618444846
{a} / (1+5+(4*8)/(124+55))  + {b} + 454 * (1+8)
({a}/(1+5+((4*8)/(124+55)))+{b}+(454*(1+8)))
```
