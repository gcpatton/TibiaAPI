<?php

namespace App\Http\Controllers;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class CharacterController extends Controller
{
    /**
     * Return the character information
     *
     * @param  string $name
     * @return \Illuminate\Http\Response
     */
    public function show($name)
    {
        $name = urldecode(str_replace(" ", "+", $name));
        $character = [];
        $deaths = [];
        $client = new Client();

        try {
            $crawler = $client->request('POST', 'https://www.tibia.com/community/?subtopic=characters', ['name' => $name]);

            $rows = array();
            $tr_elements = $crawler->filterXPath('//table/tr');
            foreach ($tr_elements as $i => $content) {
                $tds = array();
                $crawler = new Crawler($content);
                foreach ($crawler->filter('td') as $i => $node) {
                    $tds[] = $node->nodeValue;
                }
                $rows[] = $tds;
            }

            foreach ($rows as $value) {
                if (!empty($value[1])) {
                    if ($value[0] == "Name")
                        break;

                    if ($value[0] == "")
                        $character["Achievements"][] = $value[1];

                    $is_death = preg_match('/CET|CEST/i', $value[0]);
                    $key = $is_death ? $value[0] : substr_replace($value[0], "", -1);

                    if (strpos($key, "House") !== false) {
                        $character[$key][] = trim($value[1]);
                    } else if ($is_death) {
                        $character['Deaths'][] = array(
                            'Date' => trim($value[0]),
                            'Reason' => trim($value[1]),
                        );
                    } else {
                        $character[$key] = trim($value[1]);
                    }
                }
                if (strpos($value[0], "does not exist.") !== false) {
                    return response()->json(["error" => ["code" => 404, "message" => "Not found."]], 404);
                }
            }

            foreach ($character as $key => $value) {
                
                if (is_string($value) && strpos($value, "Name:") !== false) {
                    unset($character[$key]);
                }
                if (preg_match("/[0-9]+\.\\S/", $key)) {
                    $characterkey = substr(preg_replace("/[0-9]+\.\\S/", '', $key), 1);

                    $character['Account Characters'][$characterkey] = $value;
                    unset($character[$key]);
                }
            }

            if (array_key_exists('Comment', $character))
            {
                $character["Comment"] =  preg_replace("/\r|\n/", " ", $character["Comment"]);
            }
            
            unset($character[""]);
            // $character["Deaths"] = $deaths;

            return response()->json($character, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Guzzle\Http\Exception\BadResponseException $e) {

            return response()->json(["error" => "Unknown error."], 504);
        }
    }
}
