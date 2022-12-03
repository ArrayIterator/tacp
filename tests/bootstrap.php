<?php

use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeAbstract;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use function KevinGH\Box\FileSystem\file_contents;

const UNIT_TEST = true;

require __DIR__ .'/../vendor/autoload.php';

/**
 * @param $file
 *
 * @return array<string, UseUse>
 */
function test_get_imports_no_functions_from_object($file) : array
{
    static $imported = [];
    $file = realpath($file)?:$file;
    if (isset($imported[$file])) {
        return $imported[$file];
    }
    $imported[$file] = [];
    /**
     * @var UseUse $node
     */
    foreach ((new NodeFinder())
        ->find(
            (new ParserFactory())
                ->create(ParserFactory::PREFER_PHP7)
                ->parse(file_contents($file)),
            function (NodeAbstract $node) {
                return $node instanceof UseUse
                       && $node->type !== Use_::TYPE_FUNCTION
                       && $node->type !== Use_::TYPE_CONSTANT;
            }
        ) as $node) {
        $key = $node->alias?->toString()?:end($node->name->parts);
        $imported[$file][strtolower($key)] = $node;
    }
    return $imported[$file];
}

/**
 * @param ReflectionClass $reflectionClass
 *
 * @return array
 */
function test_get_doc_comment_properties(ReflectionClass $reflectionClass)
{
    preg_match_all(
        '~\*\s*@property(-read|)\s+([^\s]+|)\s+\$([^\s]+)(\s)~',
        $reflectionClass->getDocComment(),
        $match
    );

    $tags = [];
    foreach ($match[3] as $key => $item) {
        $nullable = $match[1][$key] && (
            preg_match('~([|]|^)(null|\?[a-z_].*)([|]|$)~i', $match[1][$key])
        );
        $validate = function ($result) use ($key, $match, $reflectionClass, $nullable) : bool {
            $returnType = array_map('trim', explode('|', (string) $match[2][$key]));
            if (count($returnType) === 0) {
                return true;
            }
            if (is_null($result)) {
                return $nullable;
            }

            $returnTypeLower = array_map('strtolower', $returnType);
            $returnTypeLower = array_map(fn ($e) => ltrim($e, '\\?'), $returnTypeLower);
            $returnTypeLowerString = implode('|', $returnTypeLower);
            if (is_bool($result)) {
                return (bool) preg_match('~([|]|^)(bool(ean)?|true|false)([|]|$)~', $returnTypeLowerString);
            }
            if ($result instanceof Closure) {
                return preg_match('~([|]|^)(callable|closure)([|]|$)~', $returnTypeLowerString);
            }
            if (is_iterable($result)) {
                return $result instanceof Traversable || preg_match(
                    '~([|]|^)(array|iterable)([|]|$)~',
                    $returnTypeLowerString
                );
            }
            if (is_float($result)) {
                return preg_match(
                    '~([|]|^)(float|double)([|]|$)~',
                    $returnTypeLowerString
                );
            }
            if (is_int($result)) {
                return preg_match(
                    '~([|]|^)(integer|int)([|]|$)~',
                    $returnTypeLowerString
                );
            }
            if (!is_object($result)) {
                $type = gettype($result);
                return preg_match(
                    '~([|]|^)('.$type.')([|]|$)~',
                    $returnTypeLowerString
                );
            }
            $refObject = new ReflectionObject($result);
            $lowerClassName = strtolower($refObject->getName());
            $imported = test_get_imports_no_functions_from_object($reflectionClass->getFileName());
            foreach ($imported as $import) {
                $name = $import->name->toString();
                $nameLower = strtolower($name);
                if ($nameLower === $lowerClassName) {
                    return true;
                }
                if ($refObject->isSubclassOf($import->name->toString())) {
                    return true;
                }
            }
            return false;
        };
        $tags[$item] = [
            'is_read' => $match[1][$key] !== '',
            'name' => $item,
            'return' => $match[2][$key],
            'is_nullable' => $nullable,
            'validation' => $validate
        ];
    }
    return $tags;
}
