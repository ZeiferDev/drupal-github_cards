<?php

namespace Drupal\Tests\github_cards\Unit\Service;

use Drupal;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\github_cards\Service\GitHubCardsInfoService;
use Drupal\Tests\UnitTestCase;
use Github\Api\Repo;
use Github\Api\User;
use Github\Client;

class GitHubCardsInfoServiceTest extends UnitTestCase {

  /**
   * Container builder helper.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  protected $testUserName;

  protected $testRepoName;

  public function testParseResourceUrl() {
    $ghc = GitHubCardsInfoService::create($this->container);

    $user_name = $this->randomMachineName();
    $repo_name = $this->randomMachineName();

    $checks = [
      1234 => FALSE,
      '' => FALSE,
      'http://example.com/' => FALSE,
      'http://example.com/ test' => FALSE,
      sprintf('http://example.com/%s', $user_name) => [
        'type' => 'user',
        'user' => $user_name,
        'repository' => NULL,
      ],
      sprintf('http://example.com/%s/', $user_name) => [
        'type' => 'user',
        'user' => $user_name,
        'repository' => NULL,
      ],
      sprintf('http://example.com/%s/%s', $user_name, $repo_name) => [
        'type' => 'repository',
        'user' => $user_name,
        'repository' => $repo_name,
      ],
      sprintf('http://example.com/%s/%s/', $user_name, $repo_name) => [
        'type' => 'repository',
        'user' => $user_name,
        'repository' => $repo_name,
      ],
      sprintf('http://example.com/%s/%s/anything', $user_name, $repo_name) => [
        'type' => 'repository',
        'user' => $user_name,
        'repository' => $repo_name,
      ],
    ];

    foreach ($checks as $url => $expected) {
      $this->assertEquals($expected, $ghc->parseResourceUrl($url), 'Failure checking ' . $url);
    }
  }

  public function testGetUserInfoByUrl() {
    $ghc = GitHubCardsInfoService::create($this->container);

    $url = sprintf('http://example.com/%s', $this->testUserName);
    $this->assertEquals($this->getUserInfo($this->testUserName), $ghc->getUserInfoByUrl($url));
    $this->assertFalse($ghc->getUserInfoByUrl(''));
  }

  protected function getUserInfo($userName) {
    return [
      'login' => $userName,
      'id' => 1234,
      'public_repos' => 24,
      'public_gists' => 24,
      'followers' => 7,
      'following' => 3,
    ];
  }

  public function testGetRepoInfoByUrl() {
    $ghc = GitHubCardsInfoService::create($this->container);

    $url = sprintf('http://example.com/%s/%s', $this->testUserName, $this->testRepoName);
    $this->assertEquals($this->getUserInfo($this->testUserName), $ghc->getUserInfoByUrl($url));
    $this->assertFalse($ghc->getUserInfoByUrl(''));
  }

  public function testGetUserInfo() {
    $ghc = GitHubCardsInfoService::create($this->container);

    $this->assertEquals($this->getUserInfo($this->testUserName), $ghc->getUserInfo($this->testUserName));
    $this->assertFalse($ghc->getUserInfo(''));
  }

  public function testGetRepositoryInfo() {
    $ghc = GitHubCardsInfoService::create($this->container);

    $this->assertEquals($this->getRepoInfo($this->testUserName, $this->testRepoName), $ghc->getRepositoryInfo($this->testUserName, $this->testRepoName));
    $this->assertFalse($ghc->getRepositoryInfo('', ''));
    $this->assertFalse($ghc->getRepositoryInfo('', NULL));
    $this->assertFalse($ghc->getRepositoryInfo($this->testUserName, NULL));
    $this->assertFalse($ghc->getRepositoryInfo($this->testUserName, ''));
  }

  protected function getRepoInfo($userName, $repoName) {
    return [
      'id' => 7890,
      'name' => $repoName,
      'full_name' => $userName . '/' . $repoName,
      'forks_count' => 13,
      'stargazers_count' => 3,
      'watchers_count' => 2,
    ];
  }

  public function testGetClient() {
    $ghc = GitHubCardsInfoService::create($this->container);
    $this->assertInstanceOf(Client::class, $ghc->getClient());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->testUserName = $this->randomMachineName();
    $this->testRepoName = $this->randomMachineName();

    $cache_default_bin = $this->getMockBuilder(CacheBackendInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $logger_channel = $this->getMockBuilder(LoggerChannelInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('cache.default', $cache_default_bin);
    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('logger.channel.github_cards', $logger_channel);
    $this->container->set('github_cards.client', $this->getMockedGitHubClient($this->testUserName, $this->testRepoName));
    Drupal::setContainer($this->container);
  }

  protected function getMockedGitHubClient($userName, $repoName) {
    $github_client = $this->getMockBuilder(Client::class)
      ->disableOriginalConstructor()
      ->setMethods(['users', 'repository'])
      ->getMock();

    $github_repo = $this->getMockBuilder(Repo::class)
      ->disableOriginalConstructor()
      ->getMock();
    $github_repo->method('show')->willReturnMap([
      [$userName, $repoName, $this->getRepoInfo($userName, $repoName)],
      [$userName, NULL, FALSE],
      [$userName, '', FALSE],
      ['', '', FALSE],
      ['', NULL, FALSE],
    ]);

    $github_users = $this->getMockBuilder(User::class)
      ->disableOriginalConstructor()
      ->getMock();
    $github_users->method('show')->willReturnMap([
      [$userName, $this->getUserInfo($userName)],
      ['', FALSE],
    ]);

    $github_client->method('users')->willReturn($github_users);

    $github_client->method('repository')->willReturn($github_repo);

    return $github_client;
  }

}
