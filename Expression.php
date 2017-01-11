<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/1/11
 * Time: 23:01
 */
class Expression
{
    const TYPE_EXPRESSION = 0;
    const TYPE_CONST = 1;
    const TYPE_VARIABLE = 2;

    const VARIABLE_NAME_PATTERN = '/^\{[a-zA-Z][a-zA-Z0-9_]*\}$/';

    public $type = 0;
    public $expressionString = '';
    public $tokens = [];
    public function __construct($str)
    {
        $this->expressionString = trim($str);
        try
        {
            $this->parseToken();
        }
        catch(\Exception $e)
        {
            throw new Exception('Expression parse failed:'.$this->expressionString.';'.$e->getMessage());
        }
    }

    public function parseToken()
    {
        $tokens = [];
        $start = 0;
        $end = strlen($this->expressionString);
        while($start < $end){
            $char = $this->expressionString[$start++];
            switch($char)
            {
                case "("://括号
                    $deep = 1;
                    $subExpression = '';

                    while($start < $end)
                    {
                        $newChar = $this->expressionString[$start++];
                        if ($newChar === '(')$deep++;
                        if ($newChar === ')')$deep--;
                        if ($deep === 0)break;
                        $subExpression .= $newChar;
                    }
                    $tokens[] = new Token($subExpression,Token::TYPE_SUB_EXPRESSION);
                    break;
                case "{"://变量
                    $var = "{";
                    while($start < $end )
                    {
                        $newChar = $this->expressionString[$start++];
                        $var .= $newChar;
                        if ($newChar === '}')break;
                    }
                    if (!preg_match(self::VARIABLE_NAME_PATTERN,$var))//检查变量名规范
                    {
                        throw new Exception('invalid variable name:'.$var);
                    }
                    $tokens[] = new Token($var,Token::TYPE_VARIABLE);
                    break;
                case "0":case "1":case "2":
                case "3":case "4":case "5":
                case "6":case "7":case "8":
                case "9":case "."://常量
                $const = $char;
                while($start < $end )
                {
                    $newChar = $this->expressionString[$start];
                    if ($newChar !== '.' && !is_numeric($newChar))
                    {
                        break;
                    }
                    $start++;
                    $const .= $newChar;
                }
                $tokens[] = new Token($const,Token::TYPE_CONST);
                break;
                case "-":
                case "+":
                    $tokens[] = new Token($char,Token::TYPE_OPERATOR);
                    break;
                case "*":case "/"://运算符
                $tokens[] = new Token($char,Token::TYPE_OPERATOR);
                break;
                case " ":case "\t"://忽略空格
                break;
                default://函数
                    break;
            }
        }

        $tokenCount = count($tokens);
        for($i = 0;$i<$tokenCount;$i++)
        {
            $lastToken = null;
            /** @var Token $token */
            $token = $tokens[$i];
            $nextToken = null;
            if ($i > 0)$lastToken = $tokens[$i-1];
            if ($i < ($tokenCount-1))$nextToken = $tokens[$i+1];

            switch ($token->type)
            {
                case Token::TYPE_OPERATOR:
                    if ($i == 0)
                    {
                        if ($token->token == '-' && $nextToken)
                        {
                            $i++;
                            if ($nextToken->type === Token::TYPE_CONST)
                            {
                                $nextToken->token = '-'.$nextToken->token;
                                $this->tokens[] = $nextToken;
                                break;
                            }
                            $this->tokens[] = Token::createSubExpressionToken('0 - '.$nextToken->getToken());
                            break;
                        }
                        else
                        {
                            throw new Exception('expression start with "'.$token->getToken().'"');
                        }
                    }
                    else
                    {
                        if ($lastToken->type === Token::TYPE_OPERATOR)
                        {
                            throw new Exception('unexpected chart:"'.$token->getToken().'"');
                        }
                        if (in_array($token->getToken(),['/','*']) && $i > 1 && $nextToken)
                        {
                            $lastToken = array_pop($this->tokens);
                            $this->tokens[] = Token::createSubExpressionToken($lastToken->getToken().$token->getToken().$nextToken->getToken());
                            $i++;
                            break;
                        }
                        $this->tokens[] = $token;
                    }
                    break;
                default:
                    $this->tokens[] = $token;
            }
        }

        $lastToken = end($this->tokens);
        if ($lastToken->type === Token::TYPE_OPERATOR)
        {
            throw new Exception('unexpected ending:"'.$lastToken->getToken().'"');
        }
    }

    public function getCompiledExpression()
    {
        $expression = '';
        foreach($this->tokens as $token)
        {
            $expression .= $token->getToken();
        }
        return '('.$expression.')';
    }

    public function calc($variables=[])
    {
        $tokenCount = count($this->tokens);
        $index = 0;
        $lastResult = $this->tokens[$index++]->calc($variables);
        while($index < $tokenCount)
        {
            $operaToken = $this->tokens[$index++];
            if ($index == $tokenCount || $operaToken->type != Token::TYPE_OPERATOR)
            {//never be there
                throw new Exception('compile error:'.var_export($this,1));
            }
            $metaToken = $this->tokens[$index++];

            switch($operaToken->token)
            {
                case '+':
                    $lastResult += $metaToken->calc($variables);
                    break;
                case '-':
                    $lastResult -= $metaToken->calc($variables);
                    break;
                case '*':
                    $lastResult *= $metaToken->calc($variables);
                    break;
                case '/':
                    $lastResult /= $metaToken->calc($variables);
                    break;
            }
        }

        return $lastResult;
    }

}

class Token
{
    const TYPE_OPERATOR = 0;
    const TYPE_VARIABLE = 1;
    const TYPE_CONST = 2;
    const TYPE_SUB_EXPRESSION = 3;
    public $token = '';
    public $type = 1;
    public function __construct($token,$type)
    {
        $this->token = $token;
        $this->type = $type;
        if ($type === self::TYPE_SUB_EXPRESSION)
        {
            $this->token = new Expression($token);
        }
    }

    public function getToken()
    {
        if ($this->type === self::TYPE_SUB_EXPRESSION)
        {
            return $this->token->getCompiledExpression();
        }
        return $this->token;
    }

    public static function createSubExpressionToken($expression)
    {
        return new Token($expression,self::TYPE_SUB_EXPRESSION);
    }

    public function calc($variables = [])
    {
        switch($this->type)
        {
            case self::TYPE_OPERATOR:
                return 0;//todo
            case self::TYPE_VARIABLE:
                if (array_key_exists($this->token,$variables))
                {
                    return $variables[$this->token];
                }
                throw new Exception('miss variable:'.$this->token);
                break;
            case self::TYPE_CONST:
                return 0+$this->token;
            case self::TYPE_SUB_EXPRESSION:
                return $this->token->calc($variables);
        }
    }



}