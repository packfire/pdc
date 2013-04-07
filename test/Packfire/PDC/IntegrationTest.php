<?php

/**
 * Packfire Dependency Checker (pdc)
 * By Sam-Mauris Yong
 *
 * Released open source under New BSD 3-Clause License.
 * Copyright (c) Sam-Mauris Yong Shan Xian <sam@mauris.sg>
 * All rights reserved.
 */

namespace Packfire\PDC;

/**
 * Integration tests comparing actual output against a specific code tree.
 *
 * @group Integration
 */
class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider fixtureProvider
     * @param string $fixture
     */
    public function testFixtures($fixture)
    {
        // TODO add non-*nix support
        $cmd = realpath(__DIR__ . '/../../../bin/pdc');
        $this->assertTrue(file_exists($cmd), 'PDC binary not found.');

        $autoload = escapeshellarg($fixture . '/autoload.php');
        $src = escapeshellarg($fixture . '/src');

        $report = `/usr/bin/env php $cmd --bootstrap=$autoload $src`;

        $this->assertStringEndsWith("-- PDC Complete --\n", $report);
        $this->assertThat($report, $this->logicalNot($this->stringContains("\nUnused:", false)));
        $this->assertThat($report, $this->logicalNot($this->stringContains("\nNo namespace found", false)));
        $this->assertThat($report, $this->logicalNot($this->stringContains("\nNot found:", false)));
    }

    /**
     * Provide path to fixtures.
     *
     * @return array
     */
    public function fixtureProvider()
    {
        $traitSupport = function_exists('trait_exists');
        $fixtures = array(
            array(__DIR__ . '/_fixtures/base'),
            array(__DIR__ . '/_fixtures/relative-namespace'),
        );

        if ($traitSupport) {
            $fixtures[] = array(__DIR__ . '/_fixtures/trait-support');
        }

        return $fixtures;
    }
}
