<?php

namespace MagicTest\MagicTest;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MagicTest\MagicTest\Grammar\Click;
use MagicTest\MagicTest\Grammar\Grammar;

class FileEditor
{
    protected static $writingTests = false;

    protected $possibleMethods = ['MagicTestManager::run', 'magic_test', 'magic', 'm('];

    /**
     * Overwrites the current browser operations on a given content with new ones based on the given Grammar.
     *
     * @param string $content
     * @param \Illuminate\Support\Collection $grammar
     * @param string $method
     * @return string
     */
    public function process(string $content, Collection $grammar, string $method): string
    {
        $arrayContent = explode("\n", $content);

        $firstAction = strtok(
            Str::of($content)
                ->after($method)
                ->after('$browser->')
                ->before("});\n")
                ->__toString(),
            "\n"
        );

        $lastAction = $this->getLastAction($arrayContent, $method);

        $newTestContent = collect([]);

        foreach ($arrayContent as $key => $line) {
            if ($this->isTestLastAction($line, $lastAction)) {
                self::$writingTests = true;
                $newTestContent[] = Str::replaceLast(";", "", $line);

                if ($this->isClickOrPress($newTestContent->last())) {
                    $newTestContent[] = Grammar::indent('->pause(500)', 4);
                }

                $newTestContent[] = $this->buildGrammar($grammar);

                if (empty($arrayContent[$key + 1])) {
                    self::$writingTests = false;
                }

                continue;
            }

            if (self::$writingTests) {
                // if we are still writing tests but the line ends with a ;, then we stop "writing tests" and skipping lines.
                if (Str::endsWith(trim($line), ';')) {
                    self::$writingTests = false;

                    continue;
                } else {
                    // if we are still writing tests, it means this line is from the original file/previous test.
                    continue;
                }
            }

            // push the remaining lines.
            $newTestContent[] = $line;
        }

        return $newTestContent->flatten()->implode("\n");
    }

    protected function isTestFirstAction(string $line, string $firstAction): bool
    {
        return Str::contains(trim($line), trim($firstAction));
    }

    protected function isTestLastAction(string $line, string $firstAction): bool
    {
        return Str::contains(trim($line), trim($firstAction));
    }

    protected function getLastAction(array $lines, string $method)
    {
        $a = [];
        $fullMethod = 'public function ' . $method;

        foreach ($lines as $key => $line) {
            if (Str::contains($line, $fullMethod)) {
                $reachedTestCase = true;
                $testCaseKey = $key;

                $breakpointKey = null;
                foreach ($lines as $bKey => $line) {
                    if (Str::contains($line, $this->possibleMethods)) {
                        $breakpointKey = $bKey;
                    }
                }
            }
        }

        $lastAction = collect($lines)->filter(function ($line, $key) use ($testCaseKey, $breakpointKey) {
            return $key > $testCaseKey && $key < $breakpointKey && Str::endsWith($line, ';');
        })->first();

        return $lastAction;
    }

    protected function buildGrammar(Collection $grammars): Collection
    {
        return $grammars->map(function (Grammar $grammar) use ($grammars) {
            $isLast = $grammar === $grammars->last();

            $needsPause = ($grammar instanceof Click && in_array($grammar->tag, ['a', 'button']));

            if ($isLast) {
                $text = [$grammar->build() . ';'];
            } else {
                $text = [$grammar->build()];

                if ($needsPause) {
                    $text[] = Grammar::indent('->pause(500)', 4);
                }
            }
            
            return implode("\n", $text);
        });
    }

    protected function isClickOrPress($line): bool
    {
        return Str::contains($line, ['click', 'clickLink', 'press']);
    }
}
