<?php


namespace unit\Ingenerator\PHPUtils\DeploymentConfig;


use Base_TestCase;
use Ingenerator\PHPUtils\DeploymentConfig\ConfigMapDeclarationParser;
use Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig;
use Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException;
use PHPUnit\Framework\TestCase;

class ConfigMapDeclarationParserTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(ConfigMapDeclarationParser::class, $this->newSubject());
    }

    public function test_it_parses_simple_value_pairs_to_simple_hash()
    {
        $this->assertSame(
            [
                DeploymentConfig::PRODUCTION => 'prod-stuff',
                DeploymentConfig::QA         => 'qa-stuff',
                DeploymentConfig::ANY        => 'fallback',
            ],
            $this->newSubject()->parse(
                [
                    [DeploymentConfig::PRODUCTION, 'prod-stuff'],
                    [DeploymentConfig::QA, 'qa-stuff'],
                    [DeploymentConfig::ANY, 'fallback'],
                ]
            )
        );
    }

    public function test_it_parses_arrays_of_environments_to_simple_hash()
    {
        $this->assertSame(
            [
                \Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::PRODUCTION => 'prod-stuff',
                DeploymentConfig::QA                                                => 'prod-stuff',
                DeploymentConfig::CI                                                => 'dev-stuff',
                \Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::DEV        => 'dev-stuff',
                DeploymentConfig::ANY                                               => 'fallback',
            ],
            $this->newSubject()->parse(
                [
                    [[\Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::PRODUCTION, \Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::QA], 'prod-stuff'],
                    [[DeploymentConfig::CI, \Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::DEV], 'dev-stuff'],
                    [\Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::ANY, 'fallback'],
                ]
            )
        );
    }

    public function test_it_supports_declarations_with_array_values()
    {
        $this->assertSame(
            [
                DeploymentConfig::PRODUCTION                                => ['array' => 'of things'],
                DeploymentConfig::QA                                        => ['array' => 'of things'],
                \Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::CI => 'dev-stuff',
            ],
            $this->newSubject()->parse(
                [
                    [[\Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::PRODUCTION, DeploymentConfig::QA], ['array' => 'of things']],
                    [[DeploymentConfig::CI], 'dev-stuff'],
                ]
            )
        );
    }

    public function test_it_throws_if_an_environment_mapped_more_than_once()
    {
        $subject = $this->newSubject();
        $this->expectException(\Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException::class);
        $this->expectExceptionMessage('Duplicate environment mapping');
        $subject->parse(
            [
                [DeploymentConfig::PRODUCTION, 'prod'],
                [[\Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::QA, DeploymentConfig::PRODUCTION], 'Oops, old stuff'],
            ]
        );
    }

    public function provider_invalid_declarations()
    {
        // Compose like this because by the time you nest the different levels of data provider cases and argument arrays
        // it's hard to see what the actual test values are...
        $invalid   = [];
        $invalid[] = ['declaration is not an array'];
        $invalid[] = [
            [[DeploymentConfig::PRODUCTION, \Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig::QA, 'array closed after value instead of before']],
        ];
        $invalid[] = [
            [DeploymentConfig::PRODUCTION, DeploymentConfig::QA, 'multiple environments not wrapped in an array'],
        ];
        $invalid[] = [
            // No value
            [DeploymentConfig::QA],
        ];
        $invalid[] = [
            [DeploymentConfig::QA, 'this is OK'],
            [[DeploymentConfig::DEV], 'also OK'],
            'whoops, is that an old fallback or something?',
        ];
        $invalid[] = [
            ['some' => 'unexpected', 'hash' => 'value'],
        ];

        return array_map(function (array $declarations) { return [$declarations]; }, $invalid);
    }

    /**
     * @dataProvider provider_invalid_declarations
     */
    public function test_it_throws_if_any_declarations_do_not_have_exactly_two_values($declarations)
    {
        $subject = $this->newSubject();
        $this->expectException(\Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException::class);
        $this->expectExceptionMessage('Invalid config map');
        $subject->parse($declarations);
    }

    protected function newSubject(): ConfigMapDeclarationParser
    {
        return new \Ingenerator\PHPUtils\DeploymentConfig\ConfigMapDeclarationParser;
    }

}
