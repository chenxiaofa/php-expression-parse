<?php
namespace anong\expression;
use app\exceptions\Exception;

/**
 * Created by PhpStorm.
 * User: xfachen
 * Date: 2017/1/11
 * Time: 23:01
 */
class Expression
{
    const TYPE_EXPRESSION = 0;
    const TYPE_CONST = 1;
    const TYPE_VARIABLE = 2;

    const VARIABLE_NAME_PATTERN = '/^\{[a-zA-Z][a-zA-Z0-9_]*:?[0-9]*\}$/';

    public static $validFunction = ['abs','max','min','intval','strlen','date'];
    public static $checkFunction = true;
    public static $is_pretreatment = true;
    public $type = 0;
    public $expressionString = '';
    public $tokens = [];
    private $_variables = null;
    public function __construct($str)
    {
        $this->expressionString = trim($str);
        try
        {
            $this->parseToken();
        }
        catch(\Exception $e)
        {
            throw new \Exception('Expression parse failed:'.$this->expressionString.';'.$e->getMessage());
        }
    }

    public function parseToken()
    {
        foreach(static::$validFunction as $k=>$v)
        {
            static::$validFunction[$k] = strtolower($v);
        }
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
                        self::unexpected($var);
                    }
                    $tokens[] = new Token($var,Token::TYPE_VARIABLE);
                    break;
                case "0":case "1":case "2":
                case "3":case "4":case "5":
                case "6":case "7":case "8":
                case "9":case "."://常量
                    $const = $char;
                    $hasDot = false;
                    while($start < $end )
                    {
                        $newChar = $this->expressionString[$start];
                        if ($newChar === '.')
                        {
                            if ($hasDot)
                            {
                                self::unexpected('.');
                            }
                            $hasDot = true;
                        }
                        if ($newChar !== '.' && !is_numeric($newChar))
                        {
                            break;
                        }
                        $start++;
                        $const .= $newChar;
                    }
                    $tokens[] = new Token($const,Token::TYPE_CONST);
                    break;
                case "-":case "+":case "*":
                case "/":case "%"://运算符
                    $tokens[] = new Token($char,Token::TYPE_OPERATOR);
                    break;

                case ">":case "<":case "!":case "="://逻辑运算符
                    $logicOp = $char;
                    if ($this->expressionString[$start] === '=')
                    {
                        $logicOp .= $this->expressionString[$start];
                        $start++;
                    }
                    if ($logicOp == '=')
                    {
                        self::unexpected($logicOp);
                    }

                    $tokens[] = new Token($logicOp,Token::TYPE_OPERATOR);
                    break;
                case "&":case "|":
                    $logicOp = $char;
                    if ($logicOp == $this->expressionString[$start])
                    {
                        $logicOp .= $this->expressionString[$start];
                        $start++;
                    }
                    $tokens[] = new Token($logicOp,Token::TYPE_OPERATOR);
                    break;



                case " ":case "\t":case "\r":case "\n"://忽略
                break;
                case "\"":case "'":
                    $quote = $char;
                    $string = "";
                    $lastChar = "";
                    while(1)
                    {
                        if ($start >= $end)
                        {
                            self::unexpected('end of expression');
                        }
                        $newChar = $this->expressionString[$start++];
                        if ($newChar === $quote)
                        {
                            if ($lastChar == '\\')
                            {//转义符号
                                $string = mb_substr($string,0,-1);
                            }
                            else
                            {
                                break;
                            }
                        }
                        $string.= $lastChar = $newChar;

                    }
                    $tokens[] = new Token($string,Token::TYPE_CONST,Token::FLAG_STRING);

                    break;
                default://函数
                    $functionExpress = $char;
                    $lastChar = '';
                    $newChar = '';
                    $functionName = $char;
                    while($start < $end)
                    {
                        $newChar = $this->expressionString[$start++];
                        $functionExpress .= $newChar;
                        if ($newChar === '(')break;
                        $functionName .= $newChar;
                        if ($lastChar === ' ' && $newChar !== ' ')
                        {
                            self::unexpected($functionExpress);
                        }
                        $lastChar = $newChar;
                    }

                    $deep = 1;
                    while($start < $end)
                    {
                        $newChar = $this->expressionString[$start++];
                        $functionExpress .= $newChar;
                        if ($newChar === '(')$deep++;
                        if ($newChar === ')')$deep--;
                        if ($deep === 0)break;
                    }

                    if ($newChar !== ')')
                    {
                        self::unexpected($functionExpress);
                    }
                    if (!in_array(strtolower($functionName),static::$validFunction))
                    {
                        self::unexpected($functionName);
                    }
//                    echo $functionExpress;
//
                    if (static::$checkFunction)
                    {
                        $tokens[] = new Token($functionExpress,Token::TYPE_FUNCTION_CALL);
                    }
                    else
                    {
                        $tokens[] = new Token($functionExpress,Token::TYPE_FUNCTION_CALL,Token::FLAG_IGNORE_FUNCTION_CHECK);
                    }

                    break;

            }
        }
        $logicOp1 = 0;
        $logicOp2 = 0;
        /** @var Token $token */
        foreach($tokens as $token)
        {//检查逻辑运算符数量
            if ($token->type != Token::TYPE_OPERATOR)continue;
            if (in_array($token->rawToken,['&&','||']))
            {
                $logicOp1 = 0;
                $logicOp2 = 0;
            }
            if (in_array($token->rawToken,['==','!=']))
            {
                $logicOp1++;
            }
            if (in_array($token->rawToken,['>','<','>=','<=']))
            {
                $logicOp2++;
            }
            if ($logicOp1 > 1 || $logicOp2 > 1)
            {
                self::unexpected($token->rawToken);
            }
        }
        $tokenCount = count($tokens);
        $lastToken = null;
        for($i = 0;$i<$tokenCount;$i++)
        {
            /** @var Token $lastToken */

            /** @var Token $token */
            $token = $tokens[$i];
            /** @var Token $nextToken */
            $nextToken = null;
            if ($i > 0)$lastToken = $tokens[$i-1];
            if ($i < ($tokenCount-1))$nextToken = $tokens[$i+1];

            switch ($token->type)
            {
                case Token::TYPE_OPERATOR:

                    if (in_array($token->token,['+','-','!']))
                    {
                        if ($i == 0)
                        {
                            if (!$nextToken || $nextToken->type === Token::TYPE_OPERATOR)
                            {
                                self::unexpected($token->rawToken.'"');
                            }
                            $nextToken->monadicOp = $token->token;
                            break;
                        }

                        if ($lastToken->type == Token::TYPE_OPERATOR)
                        {
                            if ($nextToken && $nextToken->type == Token::TYPE_OPERATOR)
                            {
                                self::unexpected($token->rawToken);
                            }
                            $nextToken->monadicOp = $token->token;
                            break;
                        }

                    }

                    if ($lastToken && $lastToken->type == Token::TYPE_OPERATOR)
                    {
                        $nextToken->monadicOp = $token->token;
                        break;
                    }

                    $this->tokens[] = $token;
                    $lastToken = $token;
                    break;
                default:
                    if ($lastToken && $lastToken->type != Token::TYPE_OPERATOR)
                    {
                        self::unexpected($lastToken->rawToken);
                    }
                    $this->tokens[] = $token;
                    $lastToken = $token;
                    break;
            }
        }

        $lastToken = end($this->tokens);
        if ($lastToken->type === Token::TYPE_OPERATOR)
        {
            self::unexpected($lastToken->rawToken);
        }
    }

    public function getCompiledExpression()
    {
        $expression = '';
        foreach($this->tokens as $token)
        {
            $expression .= $token->getToken();
        }
        if (count($this->tokens) == 1)
        {
            return $expression;
        }
        return '('.$expression.')';
    }

    public function calc($variables=[],$ignoreDivisionByZero=true)
    {
        try{
            return $this->_calc($variables,$ignoreDivisionByZero);
        }catch (\Exception $e)
        {
            $classes = explode('\\',get_class($e));
            if (end($classes) === 'DivisionByZeroError')
            {
                throw new DivisionByZeroError('expression calc failed:Division by zero '.$this->getCalcExpressionString($variables));
            }
            throw $e;
        }
    }
    private function _calc($variables=[],$ignoreDivisionByZero)
    {
        $values = [];
        $ops = [];
        /** @var Token $token */
        foreach($this->tokens as $token)
        {
            switch ($token->type)
            {
                case Token::TYPE_CONST:
                case Token::TYPE_FUNCTION_CALL:
                case Token::TYPE_SUB_EXPRESSION:
                case Token::TYPE_VARIABLE:
                    $values[] = $token->calc($variables);
                    break;
                case Token::TYPE_OPERATOR:
                    $ops[] = $token->getToken();
            }
//            print_r($values);
//            print_r($ops);
        }

        $final = null;

        foreach([//优先级排序
                    ['!'],
                    ['*','/','%'],
                    ['+','-'],
                    ['<','<=','>','>='],
                    ['==','!='],
                    ['&'],
                    ['^'],
                    ['|'],
                    ['&&'],
                    ['||'],
                ] as $opArray)
        {

            while($ops)
            {
                $breakWhile = 1;
                foreach($ops as $opIndex=>$op)
                {
                    if (in_array($op,$opArray))
                    {
                        $v1 = $values[$opIndex];
                        $v2 = $values[$opIndex+1];
                        switch ($op)
                        {
                            case '+':
                                $values[$opIndex] = $v1+$v2;
                                break;
                            case '-':
                                $values[$opIndex] = $v1-$v2;
                                break;
                            case '*':
                                $values[$opIndex] = $v1*$v2;
                                break;
                            case '/':
                                if ($v2 == 0)
                                {
                                    if (!$ignoreDivisionByZero)
                                    {
                                        throw new DivisionByZeroError('expression calc failed:Division by zero '.$this->expressionString);
                                    }
                                    $values[$opIndex] = false;
                                    break;
                                }
                                $values[$opIndex] = $v1/$v2;
                                break;
                            case '%':
                                if ($v2 == 0)
                                {
                                    if (!$ignoreDivisionByZero)
                                    {
                                        throw new DivisionByZeroError('expression calc failed:Division by zero '.$this->expressionString);
                                    }
                                    $values[$opIndex] = false;
                                    break;
                                }
                                $values[$opIndex] = $v1%$v2;
                                break;
                            case '>':
                                $values[$opIndex] = $v1>$v2;
                                break;

                            case '>=':
                                $values[$opIndex] = $v1>=$v2;
                                break;

                            case '<':
                                $values[$opIndex] = $v1<$v2;
                                break;

                            case '<=':
                                $values[$opIndex] = $v1<=$v2;
                                break;
                            case '!=':
                                $values[$opIndex] = $v1!=$v2;
                                break;
                            case '==':
                                $values[$opIndex] = $v1==$v2;
                                break;
                            case '&':
                                $values[$opIndex] = $v1&$v2;
                                break;
                            case '|':
                                $values[$opIndex] = $v1|$v2;
                                break;
                            case '&&':
                                $values[$opIndex] = $v1&&$v2;
                                break;
                            case '||':
                                $values[$opIndex] = $v1||$v2;
                                break;
                            default:
                                throw new \Exception('know operator '.$op);

                        }
                        array_splice($ops,$opIndex,1);
                        array_splice($values,$opIndex+1,1);
                        $breakWhile = 0;
                        break;
                    }
                }
                if ($breakWhile)
                {
                    break;
                }
            }

        }
        return $values[0];
    }


    public function getVariables()
    {
        if ($this->_variables === null)
        {
            $this->_variables = [];
            /** @var Token $token */
            foreach($this->tokens as $token)
            {
                $this->_variables = array_merge($this->_variables,$token->getVariables());
            }
        }
        return $this->_variables;
    }

    /**
     * @param $expressionString
     * @return static
     */
    public static function get($expressionString)
    {
        static $cache = [];
        $callClass = get_called_class();
        if (!array_key_exists($callClass,$cache))
        {
            $cache[$callClass] = [];
        }
        $hash = md5($expressionString);
        if (!array_key_exists($hash,$cache[$callClass]))
        {
            if ($callClass::$is_pretreatment)
            {
                $expressionString = $callClass::pretreatment($expressionString);
            }
            $cache[$callClass][$hash] = new $callClass($expressionString);
        }
        return $cache[$callClass][$hash];
    }

    public function getCalcExpressionString($variables)
    {
        $expression = $this->getCompiledExpression();
        foreach($variables as $var=>$val)
        {
            $expression = str_replace($var,str_replace('}',"(value=$val)}",$var),$expression);
        }
        return $expression;
    }

    protected static function unexpected($syntax)
    {
        throw new \Exception('syntax error, unexpected '.$syntax);
    }

    public static function pretreatment($string)
    {
        $output = preg_replace_callback('/marco:([a-zA-Z_]*)\(([^)]*)\)/i','\anong\expression\Expression::_pretreatment',$string);
//        echo $output;ob_flush();
        return $output;
    }

    public static function _pretreatment($matched)
    {
        $params = explode(',',$matched[2]);
        switch($matched[1])
        {
            case "HISTORY_SUM";
                return static::marco_history_sum($params);
                break;
            default:
                self::pretreatmentFailed('unknown marco:'.$matched[1]);
        }
    }

    private static function marco_history_sum($params)
    {
        if (count($params) != 4)
        {
            self::pretreatmentFailed('marco HISTORY_SUM expects 4 parameters');
        }
        $variable = trim($params[0]);
        $interval = $params[1];
        $start    = $params[2];
        $end      = $params[3];

        if (!preg_match('/^[a-zA-Z_0-9]+$/',$variable))
        {
            self::pretreatmentFailed('HISTORY_SUM expects parameter 1 to be string include a-z,A-Z,_,0-9 only');
        }

        if (!preg_match('/^[0-9]+$/',$interval))
        {
            self::pretreatmentFailed('HISTORY_SUM expects parameter 2 to be integer and greater than 0');
        }
        if (!preg_match('/^[0-9]+$/',$start))
        {
            self::pretreatmentFailed('HISTORY_SUM expects parameter 3 to be integer');
        }
        if (!preg_match('/^[0-9]+$/',$end))
        {
            self::pretreatmentFailed('HISTORY_SUM expects parameter 4 to be integer');
        }

        if ($start+$interval > $end)
        {
            self::pretreatmentFailed('HISTORY_SUM :invalid params');
        }

        $vars  = [];
        foreach(range($start,$end,$interval) as $history)
        {
            if ($history === 0)
            {
                $vars[] = "{{$variable}}";
            }
            else
            {
                $vars[] = "{{$variable}:{$history}}";
            }

        }
        return '('.implode('+',$vars).')';
    }

    private static function pretreatmentFailed($message)
    {
        throw new \Exception('pretreatment failed:'.$message);
    }

}