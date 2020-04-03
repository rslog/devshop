<?php

namespace DevShop\Component\GitHubCli;

class GitHubCommands extends \Robo\Tasks
{

  /**
   * @var \DevShop\Component\GitHubCli\GitHubCli
   */
  protected $cli;

  /**
   * GitHubCommands constructor.
   */
  public function __construct() {
    $this->cli = new GitHubCli();
  }

  /**
   * List available APIs.
   */
  public function listApis() {

    $this->io()->section('Available APIs');

    try {
      $apis[] = ['current_user', 'currentUser me'];
      $apis[] = ['deployment', 'deployments'];
      $apis[] = ['enterprise', 'ent'];
      $apis[] = ['git', 'git_data gitData'];
      $apis[] = ['gist', 'gists'];
      $apis[] = ['issue', 'issues'];
      $apis[] = ['markdown'];
      $apis[] = ['notification', 'notifications'];
      $apis[] = ['organization', 'organizations'];
      $apis[] = ['pull_request', 'pr pullRequest pullRequests pull_requests'];
      $apis[] = ['rateLimit', 'rate_limit'];
      $apis[] = ['repo', 'repos'];
      $apis[] = ['repository', 'repositories'];
      $apis[] = ['search'];
      $apis[] = ['team', 'teams'];
      $apis[] = ['user', 'users'];
      $apis[] = ['authorization', 'authorizations'];
      $apis[] = ['meta'];
      $this->io()->table(['API Name', 'Aliases'], $apis);
    } catch (\Exception $e) {
      $this->io()->error('Unable to list APIs: ' . $e->getMessage());
    }
  }

  /**
   * List available API methods.
   */
  public function listMethods($apiName = null) {

    if (!$apiName) {
      $apiName = $this->io()->choice('Which API?', $this->cli->getApis());
    }

    $this->io()->section('Available Methods for API ' . $apiName);

    try {
      $api = $this->cli->api($apiName);
      $this->io()->listing($this->cli->getApiMethods($api));
    } catch (\Exception $e) {
      $this->io()->error('Unable to list methods: ' . $e->getMessage());
    }
  }

  /**
   * Send an API request.
   *
   * @command api
   *
   * @param $apiName string The name of the specific API to use. See https://github.com/KnpLabs/php-github-api/blob/master/lib/Github/Client.php#L166 for available options.
   * @param $apiMethod string The API method to call. Depends on the API used. Common methods include show, create, update, remove. See the available AbstractAPI classes at https://github.com/KnpLabs/php-github-api/tree/master/lib/Github/Api.
   * @param $apiMethodArgs string All additional arguments are passed to the apiMethod.
   *
   * @see \Github\Client
   * @see \Github\Client::api()
   */
  public function api($apiName = null, $apiMethod = null, array $apiMethodArgs)
  {
     if (!$apiName) {
       $apiName = $this->io()->choice('Which API?', $this->cli->getApis());
     }

    if (!$apiMethod) {
      $apiMethod = $this->io()->choice('Which API method?', $this->cli->getApiMethods($apiName));
    }


    // Validate the API request.
     try {
       $api = $this->cli->api($apiName);
       $apiClass = get_class($api);

       // Validate that the method exists and can be called.
       if (!is_callable(array($api, $apiMethod))) {
         if (!method_exists($api, $apiMethod)) {
           throw new \InvalidArgumentException("Method $apiMethod does not exist on Class $apiClass.");
         }
         throw new \InvalidArgumentException("Method $apiMethod on Class $apiClass is private. It cannot be used.");
       }

       // Same as call_user_func_array, only faster!
       // @see https://www.php.net/manual/en/function.call-user-func-array.php#117655
       $object = $api->{$apiMethod}(...$apiMethodArgs);

       $this->io()->table(['Name', 'Value'], $this->objectToTableRows($object));

     } catch (\ArgumentCountError $e) {
       $this->io()->error('GitHub API Request failed: ' . $e->getMessage());
       return 1;

     } catch (\Exception $e) {
       $this->io()->error('GitHub API Request failed: ' . $e->getMessage());

       if ($this->io()->isDebug()) {
         $this->io()->warning($e->getTraceAsString());
       }

       return 1;
     }
//
  }

  /**
   * Show the data for the currently authenticated user. (The owner of the token.)
   *
   * @command whoami
   */
  public function whoami()
  {

    /**
     * @var \Github\Api\CurrentUser
     */
    $user = $this->cli->api('me')->show();

    // @TODO: Add a "format" option to return json, yml, or pretty
    $this->io()->table(['Name', 'Value'], $this->objectToTableRows($user));
    return 0;
  }

  /**
   * Prepare an object for display in the CLI.
   * @param $obj
   *
   * @return array
   */
   private function objectToTableRows($obj) {
    $rows = [];
    foreach ($obj as $name => $value) {
      if (!is_array($value)) {
        $rows[] = [$name, $value];
      }
    }
    return $rows;
  }
}
