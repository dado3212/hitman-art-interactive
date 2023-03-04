<?php

use BusinessLogic\Authentication\Discord\DiscordAuthenticationException;
use BusinessLogic\Authentication\Discord\UserNotInServerException;
use BusinessLogic\Caching\CacheClient;
use BusinessLogic\Caching\KeyBuilder;
use BusinessLogic\MissionType;
use Config\Constants;
use Config\Settings;
use Controllers\NodeController;
use Controllers\ViewModels\AlertMessage;
use Controllers\ViewModels\ApiResponseModel;
use Controllers\ViewModels\LoginViewModel;
use Controllers\ViewModels\MissionViewModel;
use Controllers\ViewModels\NodeNoteViewModel;
use Controllers\ViewModels\NodeWithNotesViewModel;
use DataAccess\Models\Game;
use DataAccess\Models\Location;
use DataAccess\Models\MapFloorToName;
use DataAccess\Models\Mission;
use DataAccess\Models\MissionVariant;
use DataAccess\Models\Node;
use DataAccess\Models\NodeCategory;
use DataAccess\Models\NodeDifficulty;
use DataAccess\Models\NodeNote;
use DI\Container;
use Doctrine\ORM\EntityManager;
use Klein\Request;
use Klein\Response;
use Predis\Client;

require __DIR__ . '/autoload.php';

$klein = new \Klein\Klein();

$klein->respond(function(Request $request, Response $response) use ($applicationContext) {
    if(isset($_SERVER['HTTP_ORIGIN'])) {
        $response->header('Access-Control-Allow-Origin', $_SERVER['HTTP_ORIGIN']);
    }
    $response->header('Access-Control-Allow-Headers', 'content-type,Authorization,x-readme-api-explorer,x-api-version');
    $response->header('Access-Control-Allow-Credentials', 'true');
    $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
});

// Public API calls
$klein->respond('GET', '/api/v1/games/[:game]?', function(Request $request, Response $response) use ($applicationContext) {
    if ($request->game === null) {
        $games = $applicationContext->get(EntityManager::class)->getRepository(Game::class)->findAll();
    } else {
        $games = $applicationContext->get(EntityManager::class)->getRepository(Game::class)->findBy(['slug' => $request->game]);
    }

    return $response->json($games);
});

$klein->respond('GET', '/api/v1/games/[:game]/locations/[:location]?', function (Request $request, Response $response) use ($applicationContext) {
    $game = $applicationContext->get(EntityManager::class)->getRepository(Game::class)->findOneBy(['slug' => $request->game]);

    if ($game === null) {
        $response->code(400);
        return $response->json([
            'message' => "Could not find game '{$request->game}'"
        ]);
    }

    if ($request->location === null) {
        $locations = $applicationContext->get(EntityManager::class)->getRepository(Location::class)->findBy(['game' => $request->game], ['order' => 'ASC']);
        foreach ($locations as $location) {
            $missions = $applicationContext->get(EntityManager::class)->getRepository(Mission::class)->findActiveMissionsByLocation($location->getId());
            /* @var $mission Mission */
            foreach ($missions as $mission) {
                $mission->setIcon();
                $mission->difficulties = array_map(fn(MissionVariant $mv) => $mv->getVariant(), $mission->getVariants()->toArray());
                $mission->supportsFreelancer = false;

                foreach ($mission->getVariants()->toArray() as $variant) {
                    /* @var $variant MissionVariant */
                    if ($variant->isVisible() && str_contains($variant->getSlug(), 'freelancer')) {
                        $mission->supportsFreelancer = true;
                        break;
                    }
                }
                unset($mission->floorNames);
            }
            $location->missions = $missions;
        }

    } else {
        $locations = $applicationContext->get(EntityManager::class)->getRepository(Location::class)->findBy(['game' => $request->game, 'slug' => $request->location]);
    }

    return $response->json($locations);
});

$klein->respond('GET', '/api/v1/games/[:game]/locations/[:location]/missions/[:mission]?', function(Request $request, Response $response) use ($applicationContext) {
    /* @var $location Location */
    $location = $applicationContext->get(EntityManager::class)->getRepository(Location::class)->findOneBy(['game' => $request->game, 'slug' => $request->location]);

    if ($location === null) {
        $response->code(400);
        return $response->json([
            'message' => "Could not find location with game '{$request->game}' and location slug '{$request->location}'"
        ]);
    }

    if ($request->mission === null) {
        $missions = $applicationContext->get(EntityManager::class)->getRepository(Mission::class)->findBy(['locationId' => $location->getId()], ['order' => 'ASC']);
    } else {
        $missions = $applicationContext->get(EntityManager::class)->getRepository(Mission::class)->findBy(['locationId' => $location->getId(), 'slug' => $request->mission], ['order' => 'ASC']);
    }

    return $response->json(array_map(fn(Mission $x) => new MissionViewModel($x), $missions));
});

function userIsAdmin(Request $request, Container $applicationContext, ?string &$newToken): bool {
    if (!userIsLoggedIn($request, $applicationContext, $newToken)) {
        return false;
    }

    $token = str_replace('Bearer ', '', $request->headers()->get('Authorization'));
    $userContext = getUserContextForToken($token, $applicationContext);

    return in_array(1, $userContext->getRolesAsInts());
}

//region Mission Variants
$klein->respond('POST', '/api/v1/mission-variants', function(Request $request, Response $response) use ($applicationContext) {
    $newToken = null;
    if (!userIsAdmin($request, $applicationContext, $newToken)) {
        return $response->code(401)->json(['message' => 'You must be logged in to make make edits to maps!']);
    }

    $body = json_decode($request->body(), true);
    $entityManager = $applicationContext->get(EntityManager::class);
    $mission = $entityManager->getRepository(Mission::class)->findOneBy(['id' => intval($body['missionId'])]);
    if ($mission === null) {
        return $response->code(404)->json(['message' => 'Mission not found.']);
    }

    $missionVariant = new MissionVariant();
    $missionVariant->setVariant($body['name']);
    $missionVariant->setMission($mission);
    $missionVariant->setIcon($body['icon']);
    $missionVariant->setSlug($body['slug']);
    $missionVariant->setDefault(false);
    $missionVariant->setVisible($body['visible']);
    $entityManager->persist($missionVariant);
    $entityManager->flush();

    $entityManager->getConnection()->executeQuery("INSERT INTO `node_to_mission_variants` (`node_id`, `variant_id`)
        SELECT `node_id`, {$missionVariant->getId()}
        FROM `node_to_mission_variants`
        WHERE `variant_id` = ".intval($body['sourceVariant']));

    $resp = new ApiResponseModel();
    $resp->token = $newToken;
    $resp->body = [];
    return $response->code(200)->json($resp);
});

$klein->respond('PUT', '/api/v1/mission-variants/[:id]', function(Request $request, Response $response) use ($applicationContext) {
    $newToken = null;
    if (!userIsAdmin($request, $applicationContext, $newToken)) {
        return $response->code(401)->json(['message' => 'You must be logged in to make make edits to maps!']);
    }

    $body = json_decode($request->body(), true);
    $entityManager = $applicationContext->get(EntityManager::class);
    $sql = "UPDATE `mission_to_difficulties`
        SET `difficulty` = ?,
            `visible` = ?,
            `icon` = ?,
            `slug` = ?
        WHERE `id` = ?";
    $stmt = $entityManager->getConnection()->prepare($sql);
    $stmt->bindParam(1, $body['name']);
    $stmt->bindParam(2, $body['visible']);
    $stmt->bindParam(3, $body['icon']);
    $stmt->bindParam(4, $body['slug']);
    $stmt->bindValue(5, intval($request->id));
    $stmt->execute();

    return $response->code(204);
});
//endregion

//region Map Data
$klein->respond('GET', '/api/v1/games/[:game]/locations/[:location]/missions/[:mission]/[:difficulty]/map', function(Request $request, Response $response) use ($applicationContext) {
    $cacheClient = $applicationContext->get(CacheClient::class);

    /* @var $game Game */
    $entityManager = $applicationContext->get(EntityManager::class);
    $game = $entityManager->getRepository(Game::class)->findOneBy(['slug' => $request->game]);

    /* @var $location Location */
    $location = $entityManager->getRepository(Location::class)->findOneBy(['game' => $request->game, 'slug' => $request->location]);

    /* @var $mission Mission */
    $mission = $entityManager->getRepository(Mission::class)->findOneBy(['locationId' => $location->getId(), 'slug' => $request->mission]);

    if ($mission === null) {
        $response->code(400);
        return $response->json([
            'message' => "Could not find mission with game '{$request->game}', location '{$request->location}', and mission slug '{$request->mission}'"
        ]);
    }

    if ($location === null) {
        $response->code(400);
        return $response->json([
            'message' => "Could not find location with game '{$request->game}' and location slug '{$request->location}'"
        ]);
    }

    if ($game === null) {
        $response->code(400);
        return $response->json([
            'message' => "Could not find game with slug '{$request->game}'"
        ]);
    }

    $cacheKey = KeyBuilder::buildKey(['map', $mission->getId(), $request->difficulty]);

    return $response->json($cacheClient->retrieve($cacheKey, function() use ($applicationContext, $request, $response, $location, $mission, $game) {
        $nodes = $applicationContext->get(NodeController::class)->getNodesForMissionV1($mission->getId(), $request->difficulty);
        $forSniperAssassin = $mission->getMissionType() === MissionType::SNIPER_ASSASSIN;
        $nodeCategories = $applicationContext->get(EntityManager::class)->getRepository(NodeCategory::class)->findBy(
            ['forMission' => !$forSniperAssassin, 'forSniperAssassin' => $forSniperAssassin],
            ['order' => 'ASC', 'group' => 'ASC']);

        return [
            'game' => $game,
            'mission' => $mission,
            'nodes' => $nodes,
            'searchableNodes' => $applicationContext->get(NodeController::class)->getNodesForMissionV1($mission->getId(), $request->difficulty, true, true),
            'categories' => $nodeCategories];
    }));
});

$klein->respond('GET', '/api/v2/games/[:game]/locations/[:location]/missions/[:mission]/nodes', function(Request $request, Response $response) use ($applicationContext) {
    $mission = getMissionFromRequest($applicationContext, $request);
    if ($mission === null) {
        $response->code(400);
        return $response->json([
            'message' => "Could not find mission with game '{$request->game}', location '{$request->location}', and mission slug '{$request->mission}'"
        ]);
    }

    $nodes = $applicationContext->get(NodeController::class)->getNodesForMissionV2($mission->getId());
    $forSniperAssassin = $mission->getMissionType() === MissionType::SNIPER_ASSASSIN;
    $nodeCategories = $applicationContext->get(EntityManager::class)->getRepository(NodeCategory::class)->findBy(
        ['forMission' => !$forSniperAssassin, 'forSniperAssassin' => $forSniperAssassin],
        ['order' => 'ASC', 'group' => 'ASC']);

    return $response->json([
        'topLevelCategories' => [
            'Wall Hangings',
            'Navigation'
        ],
        'nodes' => $nodes,
        'categories' => $nodeCategories
    ]);
});

function getMissionFromRequest(Container $applicationContext, Request $request): ?Mission {
    /* @var $game Game */
    $entityManager = $applicationContext->get(EntityManager::class);
    $game = $entityManager->getRepository(Game::class)->findOneBy(['slug' => $request->game]);

    /* @var $location Location */
    $location = $entityManager->getRepository(Location::class)->findOneBy(['game' => $request->game, 'slug' => $request->location]);

    return $location === null ?
        null :
        $entityManager->getRepository(Mission::class)->findOneBy(['locationId' => $location->getId(), 'slug' => $request->mission]);
}
//endregion

$klein->respond('GET', '/api/v1/editor/templates', function(Request $request, Response $response) use ($applicationContext) {
    $templates = $applicationContext->get(EntityManager::class)->getRepository(\DataAccess\Models\Item::class)->findBy([], ['name' => 'ASC']);
    $sortedTemplates = [];

    /* @var $template \DataAccess\Models\Item */
    foreach ($templates as $template) {
        if (!key_exists($template->getType(), $sortedTemplates)) {
            $sortedTemplates[$template->getType()] = [];
        }

        $sortedTemplates[$template->getType()][] = $template;
    }

    return $response->json($sortedTemplates);
});

$klein->respond('GET', '/api/v1/editor/icons', function(Request $request, Response $response) use ($applicationContext) {
    $icons = $applicationContext->get(EntityManager::class)->getRepository(\DataAccess\Models\Icon::class)->findBy([], ['order' => 'ASC', 'icon' => 'ASC']);
    $sortedIcons = [];

    /* @var $icon \DataAccess\Models\Icon */
    foreach ($icons as $icon) {
        if (!key_exists($icon->getGroup(), $sortedIcons)) {
            $sortedIcons[$icon->getGroup()] = [];
        }

        $sortedIcons[$icon->getGroup()][] = $icon;
    }

    return $response->json($sortedIcons);
});

// Web APIs
$klein->respond('GET', '/api/web/home', function(Request $request, Response $response) use ($applicationContext) {
    $games = $applicationContext->get(EntityManager::class)->getRepository(Game::class)->findAll();

    /* @var $missionRepository \DataAccess\Repositories\MissionRepository */
    $missionRepository = $applicationContext->get(EntityManager::class)->getRepository(Mission::class);

    $settings = new Settings();

    return $response->json([
        'games' => $games,
        'environment' => $settings->loggingEnvironment
    ]);
});

$klein->respond('POST', '/api/web/user/login', function(Request $request, Response $response) use ($applicationContext, $klein) {
    $controller = $applicationContext->get(\Controllers\AuthenticationController::class);

    try {
        // Temporary hack
        $token = $controller->loginUser('t', 'f'); // $_POST['tokenType'], $_POST['accessToken']);

        $responseModel = new ApiResponseModel();
        $responseModel->token = $token;
        return $response->json($responseModel);
    } catch (DiscordAuthenticationException | UserNotInServerException $e) {
        $viewModel = new LoginViewModel();
        if ($e instanceof DiscordAuthenticationException) {
            $viewModel->messages[] = new AlertMessage('danger', $e->getMessage(), 'error-discord-auth');
        } else {
            $viewModel->messages[] = new AlertMessage('danger', $e->getMessage(), 'error-not-in-server');
        }

        $responseModel = new ApiResponseModel();
        $responseModel->token = null;
        $responseModel->data = $viewModel;
        return $response->json($responseModel);
    }
});

$klein->respond('POST', '/api/nodes', function (Request $request, Response $response) use ($applicationContext) {
    $newToken = null;
    if (!userIsLoggedIn($request, $applicationContext, $newToken)) {
        print json_encode(['message' => 'You must be logged in to make make edits to maps!']);
        return $response->code(401);
    }


    $user = getUserContextForToken($newToken, $applicationContext);
    /* @var $node Node */
    $body = json_decode($request->body(), true);
    $node = $applicationContext->get(NodeController::class)->createNode($body, $user);

    $response->code(201);

    $responseModel = new ApiResponseModel();
    $responseModel->token = $newToken;
    $responseModel->data = transformNode($node);
    return json_encode($responseModel);
});

$klein->respond('PUT', '/api/nodes/[:nodeId]', function(Request $request, Response $response) use ($applicationContext) {
    $newToken = null;
    if (!userIsLoggedIn($request, $applicationContext, $newToken)) {
        print json_encode(['message' => 'You must be logged in to make make edits to maps!']);
        return $response->code(401);
    }


    $user = getUserContextForToken($newToken, $applicationContext);
    /* @var $node Node */
    $body = json_decode($request->body(), true);
    $node = $applicationContext->get(NodeController::class)->editNode($request->nodeId, $body, $user);

    $responseModel = new ApiResponseModel();
    $responseModel->token = $newToken;
    $responseModel->data = transformNode($node);
    return $response->json($responseModel);
});

function transformNode(Node $node): NodeWithNotesViewModel {
    $nodeViewModel = new NodeWithNotesViewModel();

    /* @var $note NodeNote */
    foreach ($node->getNotes()->toArray() as $note) {
        $innerViewModel = new NodeNoteViewModel();
        $innerViewModel->id = $note->getId();
        $innerViewModel->type = $note->getType();
        $innerViewModel->text = $note->getText();

        $nodeViewModel->notes[] = $innerViewModel;
    }

    $nodeViewModel->id = $node->getId();
    $nodeViewModel->missionId = $node->getMissionId();
    $nodeViewModel->type = $node->getType();
    $nodeViewModel->icon = $node->getIcon();
    $nodeViewModel->subgroup = $node->getSubgroup();
    $nodeViewModel->name = $node->getName();
    $nodeViewModel->target = $node->getTarget();
    $nodeViewModel->searchable = $node->isSearchable();
    unset($nodeViewModel->targetIcon);
    unset($nodeViewModel->difficulty);
    unset($nodeViewModel->approved);

    $nodeViewModel->level = $node->getLevel();
    $nodeViewModel->latitude = $node->getLatitude();
    $nodeViewModel->longitude = $node->getLongitude();
    $nodeViewModel->group = $node->getGroup();
    $nodeViewModel->image = $node->getImage();
    unset($nodeViewModel->tooltip);
    $nodeViewModel->objectHash = $node->getObjectHash();

    /* @var $missionVariant MissionVariant */
    foreach ($node->getVariants()->toArray() as $missionVariant) {
        $nodeViewModel->variants[] = $missionVariant->getId();
    }

    return $nodeViewModel;
}

$klein->respond('DELETE', '/api/nodes/[:nodeId]', function(Request $request, Response $response) use ($applicationContext) {
    $newToken = null;
    if (!userIsLoggedIn($request, $applicationContext, $newToken)) {
        print json_encode(['message' => 'You must be logged in to modify nodes!']);
        return $response->code(401);
    }

    /* @var $node Node */
    $node = $applicationContext->get(EntityManager::class)->getRepository(Node::class)->findOneBy(['id' => $request->nodeId]);
    if ($node === null) {
        $response->code(404);
        return $response->json(['message' => 'Could not find the node to delete!']);
    }
    $applicationContext->get(EntityManager::class)->remove($node);
    $applicationContext->get(EntityManager::class)->flush();

    $responseModel = new ApiResponseModel();
    $responseModel->token = $newToken;
    $responseModel->data = ['message' => 'Node deleted!'];

    return $response->json($responseModel);
});

function clearAllMapCaches(int $missionId, Container $applicationContext) {
    $cacheClient = $applicationContext->get(CacheClient::class);
    $cacheClient->delete([KeyBuilder::buildKey(['map', $missionId, 'standard']),
        KeyBuilder::buildKey(['map', $missionId, 'professional']),
        KeyBuilder::buildKey(['map', $missionId, 'master'])]);
}

$klein->respond('PATCH', '/api/nodes/[:nodeId]', function (Request $request, Response $response) use ($applicationContext) {
    $newToken = null;
    if (!userIsLoggedIn($request, $applicationContext, $newToken)) {
        print json_encode(['message' => 'You must be logged in to make make/suggest edits to maps!']);
        return $response->code(401);
    }

    $body = json_decode($request->body(), true);
    $applicationContext->get(NodeController::class)->moveNode(intval($request->nodeId), $body['latitude'], $body['longitude']);


    $responseModel = new ApiResponseModel();
    $responseModel->token = $newToken;
    $responseModel->data = ['message' => 'OK'];
    return $response->json($responseModel);
});

/**
 * @deprecated Should use /api/games/[:game]/locations/[:location]/missions/[:mission]/[:difficulty]/map instead
 */
$klein->respond('GET', '/api/nodes', function () use ($applicationContext) {
    $nodes = $applicationContext->get(NodeController::class)->getNodesForMissionV1($_GET['missionId'], $_GET['difficulty']);
    $nodeCategories = $applicationContext->get(EntityManager::class)->getRepository(NodeCategory::class)->findAll();

    return json_encode([
        'nodes' => $nodes,
        'categories' => $nodeCategories]);
});

// Backend processes
$klein->respond('GET', '/api/sitemap.txt', function(Request $request, Response $response) use ($applicationContext) {
    $constants = new Constants();
    $pages = [];
    // Static Pages
    $pages[] = $constants->siteDomain;
    $pages[] = "{$constants->siteDomain}/terms-of-use";
    $pages[] = "{$constants->siteDomain}/privacy-policy";
    // Location Select
    /* @var $locationRepository \DataAccess\Repositories\LocationRepository */
    /* @var $missionRepository \DataAccess\Repositories\MissionRepository */
    $entityManager = $applicationContext->get(EntityManager::class);
    $locationRepository = $entityManager->getRepository(Location::class);
    $missionRepository = $entityManager->getRepository(Mission::class);
    /* @var $games Game[] */
    $games = $entityManager->getRepository(Game::class)->findAll();
    foreach ($games as $game) {
        $pages[] = "{$constants->siteDomain}/games/{$game->getSlug()}";

        // Get locations
        /* @var $locations Location[] */
        $locations = $locationRepository->findByGame($game->getSlug());
        foreach ($locations as $location) {
            $pages[] = "{$constants->siteDomain}/games/{$game->getSlug()}#{$location->getSlug()}";

            /* @var $missions Mission[] */
            $missions = $missionRepository->findActiveMissionsByLocation($location->getId());
            foreach ($missions as $mission) {
                $pages[] = "{$constants->siteDomain}/games/{$game->getSlug()}/{$location->getSlug()}/{$mission->getSlug()}";
            }
        }
    }

    $response->header('Content-Type', 'text/plain');
    $pagesTxt = implode("\n", $pages);

    return $response->body($pagesTxt);
});

/* Admin Endpoints */
$klein->respond('GET', '/api/admin/migrate', function() {
    $config = new Config\Settings();
    if ($config->accessKey !== $_GET['access-key']) {
        return http_response_code(404);
    }

    $wrapper = new \Phinx\Wrapper\TextWrapper(new \Phinx\Console\PhinxApplication(), array('configuration' => __DIR__ . '/phinx.yml'));

    $output = $wrapper->getMigrate();

    if ($wrapper->getExitCode() > 0) {
        http_response_code(500);
    } else {
        http_response_code(200);
    }

    return '<pre>' . $output . '</pre>';
});

$klein->respond('DELETE', '/api/admin/cache', function(Request $request, Response $response) use ($applicationContext) {
    $config = new Config\Settings();
    if ($config->accessKey !== $_GET['access-key']) {
        return http_response_code(404);
    }

    $cacheClient = $applicationContext->get(CacheClient::class);
    $cacheClient->delete($cacheClient->keys('hitman2maps:map*'));

    return $response->code(204);
});

$klein->onHttpError(function (int $code, \Klein\Klein $router) {
    $router->response()->code($code);
    switch ($code) {
        case 403:
            $router->response()->json([
                'message' => 'Forbidden',
                'uri' => $router->request()->uri()
            ]);
            break;
        case 404:
            $router->response()->json([
                'message' => "Could not find route with URI {$router->request()->uri()}",
                'uri' => $router->request()->uri()
            ]);
            break;
        case 500:
            $router->response()->json([
                'message' => 'It appears that something went horribly wrong, and we are unable to handle your request at this time. Please try again in a few moments.',
                'uri' => $router->request()->uri()
            ]);
            break;
        default:
            $router->response()->json([
                'message' => "Welp, something unexpected happened with error code: {$code}",
                'uri' => $router->request()->uri()
            ]);
    }
});

$klein->onError(function (\Klein\Klein $klein, $msg, $type, Throwable $err) {
    error_log($err);
    \Rollbar\Rollbar::log(\Rollbar\Payload\Level::ERROR, $err);
    $klein->response()->code(500);

    $klein->response()->json([
        'message' => 'It appears that something went horribly wrong, and we are unable to handle your request at this time. Please try again in a few moments.',
        'uri' => $klein->request()->uri()
    ]);
});

$klein->dispatch();

function userIsLoggedIn(Request $request, Container $applicationContext, ?string &$outToken): bool {
    $outToken = null;

    /* @var $authorizationHeader string */
    $authorizationHeader = $request->headers()->get('Authorization');

    if ($authorizationHeader === null) {
        return false;
    }

    $tokenGenerator = $applicationContext->get(\BusinessLogic\Authentication\TokenGenerator::class);

    try {
        list($token) = sscanf($authorizationHeader, 'Bearer %s');
        $outToken = $tokenGenerator->validateAndRenewToken($token);

        return true;
    } catch (\BusinessLogic\Session\SessionException $e) {
        return false;
    }
}

function getUserContextForToken(string $token, Container $applicationContext): ?\DataAccess\Models\User {
    $tokenGenerator = $applicationContext->get(\BusinessLogic\Authentication\TokenGenerator::class);

    try {
        return $tokenGenerator->validate($token);
    } catch (\BusinessLogic\Session\SessionException $e) {
        return null;
    }
}
