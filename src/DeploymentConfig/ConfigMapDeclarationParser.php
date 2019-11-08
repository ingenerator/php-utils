<?php


namespace Ingenerator\PHPUtils\DeploymentConfig;


use Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException;

class ConfigMapDeclarationParser
{

    /**
     * Takes a shorthand config map declaration and turns it into a simple hash of all the enviroments
     *
     * e.g. given this set of declarations:
     *
     *      [[Cfg::QA, Cfg::DEV], 'my-dev-value']
     *      [Cfg::PRODUCTION, '#SECRET#asud2']
     *
     * will return a hash with the duplicate values pulled up a level like this:
     *
     * [
     *     Cfg::QA => 'my-dev-value',
     *     Cfg::DEV => 'my-dev-value',
     *     Cfg::PRODUCTION => '#SECRET#asud2'
     * ]
     *
     * This can then be used as a simple lookup table in the deployment config class
     *
     * @param array $declarations
     *
     * @return array
     */
    public function parse(array $declarations): array
    {
        $result = [];
        foreach ($declarations as $declaration) {
            list($environments, $value) = $this->validateDeclaration($declaration);
            foreach ($environments as $env) {
                if (array_key_exists($env, $result)) {
                    throw InvalidConfigException::duplicateEnvironmentMap($env);
                }
                $result[$env] = $value;
            }
        }

        return $result;
    }

    protected function validateDeclaration($declaration): array
    {
        if ( ! is_array($declaration)) {
            throw InvalidConfigException::invalidMapDeclaration('not an array');
        }

        if (count($declaration) !== 2) {
            throw InvalidConfigException::invalidMapDeclaration('must have exactly 2 elements');
        }

        if (array_keys($declaration) !== [0, 1]) {
            throw InvalidConfigException::invalidMapDeclaration('must be indexed array with keys 0, 1');
        }

        if (is_string($declaration[0])) {
            $declaration[0] = [$declaration[0]];
        }

        return $declaration;
    }
}
