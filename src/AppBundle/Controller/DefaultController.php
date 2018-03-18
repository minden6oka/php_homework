<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\DateTime;

class DefaultController extends Controller
{
    private $Path2File = "../data/assassins.txt";
    private $Data = array();

    private function getKillers()
    {
        $data = $this->get('session')->get('userData');
        $this->Data = $data;

    }
    private function ReadFile() : array
    {
        $assassins = array("Assassins" => array(),"Properties" => array("Name"));
        if(file_exists($this->Path2File)) {
            $lines = file($this->Path2File, FILE_IGNORE_NEW_LINES);
            $temp = array();

            foreach ($lines as $line) {

                if (str_split($line)[0] == '-') {
                    $assassins["Assassins"][] = $temp;
                    $temp = array();
                }

                if (strlen($line) <= 1) {
                    continue;
                }

                $keyvalue = preg_split('/::?/', $line, 2);
                if (!in_array($keyvalue[0], $assassins["Properties"])) {
                    !$assassins["Properties"][] = $keyvalue[0];
                }
                if (preg_match('/^[^:]+::/', $line)) {
                    $temp[trim($keyvalue[0])][] = trim($keyvalue[1]);
                } else {
                    $temp[trim($keyvalue[0])] = trim($keyvalue[1]);
                }
            }
        }

        $this->get('session')->set('userData',$assassins);
        return $assassins;
    }

    private function random_color_part() {
        return str_pad( dechex( mt_rand( 1, 254 ) ), 2, '0', STR_PAD_LEFT);
    }

    private function random_color() {
        return $this->random_color_part() . $this->random_color_part() . $this->random_color_part();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/killers/extend",name="killersextend")
     */
    public function extendAction(Request $request){
        $this->getKillers();
        if(!$this->Data){
            $this->Data = $this->ReadFile();
        }

        $values = array();

        foreach ($this->Data['Assassins'] as $killer){
            foreach (array_keys($killer) as $prop){
                if(array_key_exists($prop,$values)){
                    if (is_iterable($killer[$prop])){
                        foreach($killer[$prop] as $value){
                            if(!in_array($value,$values[$prop])){
                                $values[$prop][] = $value;
                            }
                        }
                    }elseif(!in_array($killer[$prop],$values[$prop])){
                        $values[$prop][] = $killer[$prop];
                    }
                }else{
                    if (is_iterable($killer[$prop])){
                        foreach ($killer[$prop] as $value){
                            $values[$prop][] = $value;
                        }
                    }else{
                        $values[$prop][] = $killer[$prop];
                    }
                }
            }
        }

        for ($i = 0; $i < 10;$i++){
            $propertynumber = random_int(0,count($this->Data['Properties']) - 1);
            $curProperties = array();
            $curProperties += $this->Data['Properties'];

            for ($j = 0; $j < $propertynumber; $j++){
                $temp = random_int(0,count($curProperties) - 1);
                unset($curProperties[$temp]);
                $curProperties = array_values($curProperties);
            }

            $curKiller = array();

            foreach ($curProperties as $prop){
                $posValues = array_values($values[$prop]);


                if (in_array($prop, ['Victims','Weapons'])){
                    $selValueNum = random_int(1,count($posValues));
                    for($k = 1; $k < $selValueNum;$k++){
                        $loc = random_int(0,count($posValues) - 1);
                        $curKiller[$prop][] = $posValues[$loc];
                        unset($posValues[$loc]);
                        $posValues = array_values($posValues);
                    }
                }else{
                    $curKiller[$prop] = $posValues[random_int(0,count($posValues) - 1)];
                }
            }
            $this->Data['Assassins'][] = $curKiller;
        }

        $this->get('session')->set('userData',$this->Data);

        return $this->redirectToRoute('killers');
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/killers/latest",name="killerslatest")
     */
    public function latestAction(Request $request){
        $this->getKillers();
        if(!$this->Data){
            $this->Data = $this->ReadFile();
        }

        $Latest = array();
        foreach ($this->Data['Assassins'] as $killer){
            $date = '00000000';
            if (array_key_exists('LastAssassination',$killer)){
                $date = intval(mb_ereg_replace('/-/','' ,$killer['LastAssassination']));
            }
            $Latest[$date][] = $killer;
        }


        rsort($Latest);

        $Latest = array_pop($Latest);

        return $this->render('killers/latest.html.twig',["Assassins" => $this->Data["Assassins"],"Properties" => $this->Data["Properties"],"Latest" => $Latest]);
    }


    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/killers/richest",name="killersrichest")
     */
    public function richestAction(Request $request){
        $this->getKillers();
        if(!$this->Data){
            var_dump(0);
            $this->Data = $this->ReadFile();
        }

        $ranks = array();

        foreach ($this->Data['Assassins'] as $killer){
            $name = array_key_exists("Name",$killer) ? $killer['Name'] : "Unknown";
            $value = array_key_exists("AveragePrice",$killer) && array_key_exists('Victims',$killer) && count($killer['Victims']) > 0 ? intval((count($killer['Victims']) * intval($killer['AveragePrice']))) : 0;
            if(array_key_exists($value,$ranks)){
                $ranks[$value] .= ", {$name}";
            }else{
                $ranks[$value] = $name;
            }
        }

        krsort($ranks);

        return $this->render('killers/richest.html.twig',["Assassins" => $this->Data["Assassins"],"Properties" => $this->Data["Properties"],"Ranks" => $ranks]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/killers/averages",name="killersaverages")
     */
    public function averageAction(Request $request){
        $this->getKillers();
        if(!$this->Data){
            $this->Data = $this->ReadFile();
        }

        $averageArray = array();

        foreach ($this->Data["Assassins"] as $killer){
            foreach ($this->Data["Properties"] as $property) {
                $value = 0;
                if (array_key_exists($property,$killer)){
                    if(is_iterable($killer[$property])){
                        $value = count($killer[$property]);
                    }
                    elseif (ctype_digit($killer[$property])){
                        $value = intval($killer[$property]);
                    }elseif(($date = date_parse_from_format("Y-m-d",$killer[$property]))["year"]){
                        $value = time($date);
                    }else{
                        $value = strlen($killer[$property]);
                    }
                }
                if(!array_key_exists($property,$averageArray)){
                    $averageArray[$property] = ["Count" => 1,"value" => $value];
                }else{
                    $averageArray[$property]["value"] += $value;
                    $averageArray[$property]["Count"]++;
                }
            }
        }



        return $this->render('killers/average.html.twig',["Assassins" => $this->Data["Assassins"],"Properties" => $this->Data["Properties"],"Averages" => $averageArray]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/killers/min",name="killersmin")
     */
    public function minAction(Request $request){
        $this->getKillers();
        if(!$this->Data){
            $this->Data = $this->ReadFile();
        }

        $maxArray = array();

        foreach ($this->Data["Assassins"] as $killer){
            foreach ($this->Data["Properties"] as $property) {
                if (array_key_exists($property,$killer)){
                    if(is_iterable($killer[$property])){
                        $value = count($killer[$property]);
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "a","Value" => $value];
                        }elseif($value < $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                        }
                    }
                    elseif (ctype_digit($killer[$property])){
                        $value = intval($killer[$property]);
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "i","Value" => $value];
                        }elseif($value < $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                        }
                    }elseif(($date = date_parse_from_format("Y-m-d",$killer[$property]))["year"]){
                        $value = intval(mb_ereg_replace('/-/','',$killer[$property]));
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "d","Value" => $value,"Format"=> $killer[$property]];
                        }elseif($value < $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                            $maxArray[$property]["Format"] = $killer[$property];
                        }
                    }else{
                        $value = strlen($killer[$property]);
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "s","Value" => $value,"Format"=> [$killer[$property]]];
                        }elseif($value < $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                            $maxArray[$property]["Format"][] = $killer[$property];
                        }
                    }
                }
            }
        }

        foreach (array_keys($maxArray) as $key){
            $maxArray[$key]["Color"] = $this->random_color();
        }

        return $this->render('killers/min.html.twig',["Assassins" => $this->Data["Assassins"],"Properties" => $this->Data["Properties"],"maxValues" => $maxArray]);
    }
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/killers/max",name="killersmax")
     */
    public function maxAction(Request $request){
        $this->getKillers();
        if(!$this->Data){
            $this->Data = $this->ReadFile();
        }

        $maxArray = array();

        foreach ($this->Data["Assassins"] as $killer){
            foreach ($this->Data["Properties"] as $property) {
                if (array_key_exists($property,$killer)){
                    if(is_iterable($killer[$property])){
                        $value = count($killer[$property]);
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "a","Value" => $value];
                        }elseif($value > $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                        }
                    }
                    elseif (ctype_digit($killer[$property])){
                        $value = intval($killer[$property]);
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "i","Value" => $value];
                        }elseif($value > $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                        }
                    }elseif(($date = date_parse_from_format("Y-m-d",$killer[$property]))["year"]){
                        $value = intval(mb_ereg_replace('/-/','',$killer[$property]));
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "d","Value" => $value,"Format"=> $killer[$property]];
                        }elseif($value > $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                            $maxArray[$property]["Format"] = $killer[$property];
                        }
                    }else{
                        $value = strlen($killer[$property]);
                        if(!array_key_exists($property,$maxArray)){
                            $maxArray[$property] = ["Type" => "s","Value" => $value,"Format"=> [$killer[$property]]];
                        }elseif($value > $maxArray[$property]["Value"]){
                            $maxArray[$property]["Value"] = $value;
                            $maxArray[$property]["Format"][] = $killer[$property];
                        }
                    }
                }
            }
        }

        foreach (array_keys($maxArray) as $key){
            $maxArray[$key]["Color"] = $this->random_color();
        }

        return $this->render('killers/max.html.twig',["Assassins" => $this->Data["Assassins"],"Properties" => $this->Data["Properties"],"maxValues" => $maxArray]);
    }
    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/killers",name="killers")
     */
    public function killersAction(Request $request){
        $this->getKillers();
        if(!$this->Data){
            $this->Data = $this->ReadFile();
        }
        return $this->render('killers/list.html.twig',$this->Data);
    }

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('default/index.html.twig',
            [
            'base_dir' => realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR,
        ]);
    }


}
