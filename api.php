<?php
require_once("config.php");
require_once("src/autoload.php");
require_once("vendor/autoload.php");

use BlueHerons\StatTracker\Agent;
use BlueHerons\StatTracker\OCR;
use BlueHerons\StatTracker\StatTracker;

use Curl\Curl;
use Endroid\QrCode\QrCode;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$StatTracker = new StatTracker();

// Assert that token and stat parameters, if present, match expected format
$validateRequest = function(Request $request, Silex\Application $StatTracker) {
	function validateParameter($param, $regex) {
		if (strlen($param) > 0) {
			return preg_match($regex, $param) === 1;
		}
		else {
			return true;
		}
	}

	// Ensure {token} is 6 hexidecimal digits
	if (!validateParameter($request->get("token"), "/^[a-f0-9]{64}$/i")) { return $StatTracker->abort(400); }
	// Ensure {stat} is alpha characters and an underscore
	if (!validateParameter($request->get("stat"), "/^[a-z_]+$/")) { return $StatTracker->abort(400); }
};

$StatTracker->get("/api/{token}/profile/{when}.{format}", function($token, $when, $format) use ($StatTracker) {
	$agent = Agent::lookupAgentByToken($token);

	$response = new stdClass;

	$response->agent = $agent->name;
	
	$t = new stdClass;

	if ($StatTracker->isValidDate($when)) {
		$ts = $agent->getUpdateTimestamp($when, true);

		if ($ts == null) {
			return $StatTracker->abort(403);
		}
		else {
			$response->date = date("c", $ts);
			$response->badges = $agent->getBadges($when, true);
			$response->stats = $agent->getStats($when, true);
		}
	}
	else if ($when == "latest") {
		$response->date = date("c", $agent->getUpdateTimestamp());
		$response->badges = $agent->getBadges();
		$response->stats = $agent->getStats("latest", true);
	}
	else {
		return $StatTracker->abort(403);
	}

	switch ($format) {
		case "json":
			return $StatTracker->json($response);
			break;
	}
})->before($validateRequest)
  ->assert("format", "json")
  ->assert("when",   "latest|[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}")
  ->value ("format", "json")
  ->value ("when",   "latest");

// Retrieve basic information about the agent
$StatTracker->get("/api/{token}", function($token) use ($StatTracker) {
	$agent = Agent::lookupAgentByToken($token);

	if (!$agent->isValid()) {
		return $StatTracker->abort(403);
	}

	return $StatTracker->json($agent);
})->before($validateRequest);

$StatTracker->match("/api/{token}/token", function(Request $request, $token) use ($StatTracker) {
    $agent = Agent::lookupAgentByToken($token);

    if (!$agent->isValid()) {
        return $StatTracker->abort(403);
    }

    switch ($request->getMethod()) {
        case "GET":
            $name = strtoupper(substr(str_shuffle(md5(time() . $token . rand())), 0, 6));
            $token = $agent->createToken($name);

            $url = sprintf("%s://%s", $request->getScheme(), $request->getHost());
            $url = $url . $request->getBaseUrl();

            $uri = "stattracker://token?token=%s&name=%s&agent=%s&issuer=%s";
            $uri = sprintf($uri, $token, $name, $agent->name, $url);

            $qr = new QRCode();
            $qr->setText($uri)
               ->setSize(200)
               ->setPadding(10);

            if ($token === false) {
                return new Response(null, 202);
            }
            else {
                $data = array(
                    "name" => $name,
                    "token" => $token,
                    "qr" => $qr->getDataUri(),
                    "uri" => $uri
                );
                return $StatTracker->json($data);
            }
            break;
        case "DELETE":
            if (!$request->request->has("name")) {
                return $StatTracker->abort(400);
            }

            $name = strtoupper($request->request->get("name"));
            $r = $agent->revokeToken($name);
            if ($r === true) {
                return new Response(null, 200);
            }
            else {
                return new Response(null, 401);
            }
            break;
    }
})->method("GET|DELETE");

// Retrieve badge information for the agent
$StatTracker->get("/api/{token}/badges/{what}", function(Request $request, $token, $what) use ($StatTracker) {
	$agent = Agent::lookupAgentByToken($token);
	
	if (!$agent->isValid()) {
		return $StatTracker->abort(403);
	}

	$limit = is_numeric($request->query->get("limit")) ? (int)$request->query->get("limit") : 4;

	if (preg_match("/[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}/", $what)) {
		$data = $agent->getBadges($what);
	}
	else if ($what == "upcoming") {
		$data = $agent->getUpcomingBadges($limit);
	}
	else {
		$data = $agent->getBadges();
	}

	return $StatTracker->json($data);
})->before($validateRequest)
  ->assert("what", "today|upcoming|[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}")
  ->value("what", "today");

// Retrieve ratio information for the agent
$StatTracker->get("/api/{token}/ratios", function($token) use ($StatTracker) {
	$agent = Agent::lookupAgentByToken($token);

	if (!$agent->isValid()) {
		return $StatTracker->abort(403);
	}

	$data = $agent->getRatios();
	return $StatTracker->json($data);
})->before($validateRequest);

// Retrieve raw or compiled data for a single stat for the agent
$StatTracker->get("/api/{token}/{stat}/{view}/{when}.{format}", function($token, $stat, $view, $when, $format) use ($StatTracker) {
	$agent = Agent::lookupAgentByToken($token);

	if (!$agent->isValid()) {
		return $StatTracker->abort(403);
	}
        else if (!$StatTracker->isValidStat($stat)) {
                return $StatTracker->abort(404);
        }

	$data = "";
	switch ($view) {
		case "breakdown":
			$data = $agent->getAPBreakdown();
			break;
		case "leaderboard":
			$data = $StatTracker->getLeaderboard($stat, $when);
			break;
		case "prediction":
			$data = $agent->getPrediction($stat);
			break;
		case "trend":
			$data = $agent->getTrend($stat, $when);
			break;
		case "graph":
			$data = $agent->getGraphData($stat);
			break;
		case "raw":
			$agent->getStat($stat);
			$data = new stdClass();
			$data->value = $agent->stats[$stat];
			$data->timestamp = $agent->getUpdateTimestamp();
			break;
	}

	$response = JsonResponse::create();
	$response->setEncodingOptions($response->getEncodingOptions() | JSON_NUMERIC_CHECK);
	$response->setData($data);

	return $response;
})->before($validateRequest)
  ->assert("view", "breakdown|leaderboard|prediction|trend|graph")
  ->value("stat", "ap")
  ->value("view", "raw")
  ->value("when", "most-recent")
  ->value("format", "json");


// Allow agents to submit stats
$StatTracker->post("/api/{token}/submit", function($token) use ($StatTracker) {
	$agent = Agent::lookupAgentByToken($token);

	if (!$agent->isValid()) {
		return $StatTracker->abort(403);
	}

        // Filter out keys that do not represent stats
        $data = array_intersect_key($_POST, array_merge(StatTracker::getStats(), array("date"=>"")));
        $response = new stdClass();
        $response->error = false;

        try {
	    $agent->updateStats($data);
            $response->message = sprintf("Your stats for %s have been received.", date("l, F j", strtotime($data['date'])));

            if (!$agent->hasSubmitted()) {
                $response->message .= " Since this was your first submission, predictions are not available. Submit again tomorrow to see your predictions.";
            }

            $StatTracker['session']->set("agent", Agent::lookupAgentByToken($token));
        }
        catch (Exception $e) {
            $response->error = true;
            $response->message = $e->getMessage();
        }

	return $StatTracker->json($response);
})->before($validateRequest);

$StatTracker->post("/api/{token}/ocr", function(Request $request, $token) use ($StatTracker) {
	$agent = Agent::lookupAgentByToken($token);

	if (!$agent->isValid()) {
		return $StatTracker->abort(403);
	}

        $content_type = explode(";", $request->headers->get("content_type"))[0];
	$file = UPLOAD_DIR . uniqid("ocr_") . ".png";

	switch ($content_type) {
	    case "application/x-www-form-urlencoded":
	        // Not a file upload, but a POST of bytes
		$hndl = fopen($file, "w+");
		fwrite($hndl, file_get_contents("php://input"));
		fclose($hndl);
		break;
	    case "multipart/form-data":
                // Typically an HTTP file upload
                move_uploaded_file($_FILES['screenshot']['tmp_name'], $file);
                break;
            default:
	        return $StatTracker->abort(400, "Bad request " . $content_type);
		break;
	}

	$processImageAsync = function() use ($StatTracker, $file) {
	    // This method will print the results to the output stream
            $StatTracker->scanProfileScreenshot($file, true);
	};

        if (filter_var($request->query->get("async", true), FILTER_VALIDATE_BOOLEAN)) {
	    return $StatTracker->stream($processImageAsync, 200, array ("Content-type" => "application/octet-stream"));
        }
        else {
            return $StatTracker->json(array("stats" => $StatTracker->scanProfileScreenshot($file, false)));
        }

})->before($validateRequest);

$StatTracker->after(function (Request $request, Response $response) {
	$response->headers->set("Cache-control", "max-age=". (60 * 60 * 6) .", private");
	$response->headers->set("Expires", date("D, d M Y H:i:s e", time() + 60 * 60 * 6));
});

$StatTracker->run();
?>
