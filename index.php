<?php
require_once("config.php");
require_once("vendor/autoload.php");

use BlueHerons\StatTracker\Agent;
use BlueHerons\StatTracker\StatTracker;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$StatTracker = new StatTracker();

$StatTracker['controllers']->before(function(Request $request) use ($StatTracker) {
        $StatTracker->setBaseUrl($request);

	if (!is_dir(UPLOAD_DIR) || !is_writeable(UPLOAD_DIR)) {
		throw new Exception(sprintf("UPLOAD_DIR (%s) is not writeable", UPLOAD_DIR));
	}
	if (!is_dir(LOG_DIR) || !is_writeable(LOG_DIR)) {
		throw new Exception(sprintf("LOG_DIR (%s) is not writeable", LOG_DIR));
	}
});

$StatTracker->error(function(Exception $e, $code) {
	// Eventually, have a custom error page
});

// Default handler. Will match any alphnumeric string. If the page doesn't exist,
// 404
$StatTracker->get('/{page}', function ($page) use ($StatTracker) {
	if ($page == "dashboard" ||
	    $page == "leaderboards" ||
	    $page == "submit-stats" ||
            $page == "settings" ||
            $page == "terms") {
		$StatTracker['session']->set("page_after_login", $page);
		return $StatTracker['twig']->render("index.twig", array(
                        "agent" => $StatTracker->getAgent(),
			"constants" => array(
				"ga_id" => StatTracker::getConstant("GOOGLE_ANALYTICS_ID"),
                                "admin_agent" => StatTracker::getConstant("ADMIN_AGENT"),
                                "contributors" => $StatTracker->getContributors(),
                                "debug" => StatTracker::getConstant("DEBUG", false),
				"group_name" => StatTracker::getConstant("GROUP_NAME"),
				"version" => StatTracker::getConstant("VERSION", "bleeding edge"),
			),
			"stats" => $StatTracker->getStats(),
			"page" => $page
		));
	}
        else if ($page == "logout") {
            $StatTracker->getAuthenticationProvider()->logout($StatTracker);
            return $StatTracker->redirect("./");
        }
	else if ($page == "authenticate") {
		switch ($_REQUEST['action']) {
			case "login":
                                $authResponse = $StatTracker->getAuthenticationProvider()->login($StatTracker);
                                if ($authResponse->status == "registration_required") {
                                    $StatTracker->sendRegistrationEmail($authResponse->email);
                                }
				return $StatTracker->json($authResponse);
				break;
			case "callback":
				$StatTracker->getAuthenticationProvider()->callback($StatTracker);
                                $StatTracker->getAuthenticationProvider()->login($StatTracker);
				$page = $StatTracker['session']->get("page_after_login");
				$page = empty($page) ? "dashboard" : $page;
				return $StatTracker->redirect("./{$page}");
				break;
			case "token":
				$StatTracker->getAuthenticationProvider()->token($StatTracker);
				$authResponse = $StatTracker->getAuthenticationProvider()->login($StatTracker);
				return $StatTracker->json($authResponse);
				break;
			case "logout":
				return $StatTracker->json($StatTracker->getAuthenticationProvider()->logout($StatTracker));
				break;
			default:
				$StatTracker->abort(400, "Invalid authentication action");
		}
	}
	else {
		$StatTracker->abort(404);
	}
})->assert('page', '[a-z-]+')
  ->value('page', 'dashboard');

$StatTracker->get('/page/{page}', function(Request $request, $page) use ($StatTracker) {
	$page_parameters = array();
	
	if ($page == "submit-stats") {
		$date = $request->get("date");
		$date = $StatTracker->isValidDate($date) ? $date : null;
		if ($date == null || new DateTime() < new DateTime($date)) {
			$StatTracker->getAgent()->getStats("latest", true);
			$date = date("Y-m-d");
		}
		else {
			$StatTracker->getAgent()->getStats($date, true);
		}

		$page_parameters['date'] = $date;
	}

	return $StatTracker['twig']->render($page.".twig", array(
		"agent" => $StatTracker->getAgent(),
		"constants" => array(
                    "admin_agent" => StatTracker::getConstant("ADMIN_AGENT"),
                    "email_submission" => StatTracker::getConstant("EMAIL_SUBMISSION")
                ),
		"stats" => $StatTracker->getStats(),
		"faction_class" => $StatTracker->getAgent()->faction == "R" ? "resistance-agent" : "enlightened-agent",
		"faction_color" => $StatTracker->getAgent()->faction == "R" ? RES_BLUE : ENL_GREEN,
		"parameters" => $page_parameters
	));
});

$StatTracker->get("/resources/{resource_dir}/{resource}", function(Request $request, $resource) use ($StatTracker) {
	switch ($resource) {
		case "style.css":
			$file = "./resources/css/style.less";
			$lastModified = filemtime($file);
			$css = new Symfony\Component\HttpFoundation\Response("", 200, array("Content-Type" => "text/css"));
			$css->setLastModified(new \DateTime("@".filemtime($file)));

			if ($css->isNotModified($request)) {
				$css->setNotModified();
			}
			else {
				$parser = new Less_Parser(array("compress" => true));
				$parser->parseFile($file, $request->getBaseUrl());
				$css->setLastModified(new \DateTime("@".filemtime($file)));
				$css->setContent($parser->getCss());
			}

			return $css;
			break;
		case "stat-tracker.js":
			$js = new Symfony\Component\HttpFoundation\Response();

			if ($js->isNotModified($request)) {
				$js->setNotModified();
			}
			else {
				$content = $StatTracker['twig']->render("stat-tracker.js.twig");
				$js->headers->set("Content-Type", "application/javascript");
				$js->setContent($content);
			}

			return $js;
			break;
	}
});

$StatTracker->run();
?>
