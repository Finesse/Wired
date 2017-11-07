<?php

namespace Finesse\Wired\Tests\ThirdParty;

use Finesse\Wired\Tests\ModelsForTests\User;
use Finesse\Wired\Tests\TestCase;
use Finesse\Wired\ThirdParty\PagerfantaAdapter;

/**
 * Tests the PagerfantaAdapter class
 *
 * @author Surgie
 */
class PagerfantaAdapterTest extends TestCase
{
    /**
     * Tests the whole adapter
     */
    public function testAdapter()
    {
        $mapper = $this->makeMockDatabase();
        $query = $mapper->model(User::class)->where('name', '>', 'J')->orderBy('email', 'desc');
        $adapter = new PagerfantaAdapter($query);

        $this->assertEquals(17, $adapter->getNbResults());

        $users = $adapter->getSlice(12, 3);
        $this->assertInternalType('array', $users);
        $this->assertCount(3, $users);
        $this->assertAttributes(['id' => 14, 'name' => 'Nicole', 'email' => 'nicole@test.com'], $users[0]);
        $this->assertAttributes(['id' => 13, 'name' => 'Madonna', 'email' => 'madonna@test.com'], $users[1]);
        $this->assertAttributes(['id' => 12, 'name' => 'Linda', 'email' => 'linda@test.com'], $users[2]);

        $users = $adapter->getSlice(15, 5);
        $this->assertCount(2, $users);
        $this->assertAttributes(['id' => 11, 'name' => 'Kenny', 'email' => 'kenny@test.com'], $users[0]);
        $this->assertAttributes(['id' => 10, 'name' => 'Jack', 'email' => 'jack@test.com'], $users[1]);
    }
}
