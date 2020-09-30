<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Tsm;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{
    private $columns = array(
        'dlrType' => "F",'code1' => "B",
        'code2' => "D", 'code3' => "E", 'soldCode' => "W", 'shipCode' => "C", 'abbreviatedName' => "I",
        'name' => "H", 'address' => "J", 'city' => "L", 'state' => "M", 'zip' => "N", 'phone' => "P", 'fax' => "Q",
    );

    private $canadaStates = array("AB", "BC", "MB", "NB", "NL", "NT", "NS", "NU", "ON", "PE", "QC", "SK", "YT");

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $tsms = $this->getDoctrine()->getRepository('AppBundle:Tsm')->findAll();

        return $this->render('@App/Default/list.html.twig', [
            'items' => $tsms,
            'lastUpdate' => array(
                'adbuilder' => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("adbuilder"),
                'tomahawk'  => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("tomahawk"),
            )
        ]);
    }

    /**
     * @Route("/import", name="import")
     */
    public function importAction(Request $request)
    {

        return $this->render('@App/Default/import.html.twig', [

        ]);
    }

    /**
     * @Route("/upload", name="upload")
     */
    public function uploadAction(Request $request){

        $file = $request->files->get("file");
        $path = $file->getPathname();

        $offset = 2;

        $fileType = \PHPExcel_IOFactory::identify($path);
        $reader = \PHPExcel_IOFactory::createReader($fileType);
        $excel  = $reader->load($path);
        // select sheet 'Master File'
        $sheet = $excel->setActiveSheetIndex(1);

        $rows = $sheet->getHighestRow();
        $mapsStatus = "OK";

        $em = $this->getDoctrine()->getManager();

        for ($r = $offset; $r <= $rows; $r++) {

            $brand = $sheet->getCell("A".$r)->getValue();
            if($brand != "Case") continue;

            $reg = "";
            $rsd = "";
            $tsmCode = "";
            $tsmName = "";
            $country = "";
            $dealerCode = $sheet->getCell($this->columns['code1'].$r)->getValue();
            if(empty($dealerCode)) continue;

            $tsm = $this->getDoctrine()->getRepository('AppBundle:Tsm')->findOneByCode1($dealerCode);
            if(!$tsm) $tsm = new Tsm();

            foreach($this->columns as $field => $val){
                $setter = 'set'.ucfirst($field);
                $getter = 'get'.ucfirst($field);
                $cellVal = $sheet->getCell($val.$r)->getValue();
                //update letter case
                switch($field){
                    case "abbreviatedName":
                    case "name":
                    case "address":
                    case "city":
                        $cellVal = ucwords(strtolower($cellVal));
                        break;
                    case "phone":
                        if(empty($cellVal)) $cellVal = "none";
                        break;
                }
                if($tsm->$getter() != $cellVal) $tsm->$setter($cellVal);
            }

            $rsd = $sheet->getCell("AP".$r)->getValue();
            $regAndRsd = explode(" ", $rsd);
            if(!empty($regAndRsd[0])){
                $reg = $regAndRsd[0];
                $rsd = str_replace($reg." ", "", $rsd);
                $rsd = ucwords(strtolower($rsd));
            }

            $tsmName = $sheet->getCell("AQ".$r)->getValue();
            $tsmAndName = explode(" ", $tsmName);
            if(!empty($tsmAndName[0])){
                $tsmCode = $tsmAndName[0];
                $tsmName = str_replace($tsmCode." ", "", $tsmName);
                $tsmName = ucwords(strtolower($tsmName));
            }

            $country = $sheet->getCell("O".$r)->getValue();
            if($country == "USA") $country = "US";
            elseif($country == "Canada") $country = "CA";
            elseif(empty($country)){
                $state = $sheet->getCell("M".$r)->getValue();
                $state = strtoupper(trim($state));
                if(in_array($state, $this->canadaStates)) $country = "CA";
                else $country = "US";
            }

            if($reg != $tsm->getReg()) $tsm->setReg($reg);
            if($rsd != $tsm->getRsd()) $tsm->setRsd($rsd);
            if($tsmCode != $tsm->getTsm()) $tsm->setTsm($tsmCode);
            if($tsmName != $tsm->getTsmName()) $tsm->setTsmName($tsmName);
            if($country != $tsm->getCountry()) $tsm->setCountry($country);
            $tsm->setCode($dealerCode);

            if($tsm->getCountry() != ""){
                $sysCode = ($tsm->getCountry() == "US" ? "101-" : "111-").$dealerCode;
                if($tsm->getCode() != $sysCode) $tsm->setCode($sysCode);
            }

            if(!$tsm->getId()) $em->persist($tsm);
        }

        $em->flush();

        $this->addFlash(
            'notice',
            'Data was imported successfully.'
        );

        return new JsonResponse("success");
    }

    /**
     * @Route("/list", name="list")
     */
    public function listAction(){
        $tsms = $this->getDoctrine()->getRepository('AppBundle:Tsm')->findAll();

        $return = [];

        foreach($tsms as $item){
            $return[] = [
                'id'        => $item->getId(),
                'reg'       => $item->getReg(),
                'rsd'       => $item->getRsd(),
                'tsm'       => $item->getTsm(),
                'tsmName'   => $item->getTsmName(),
                'dlrType'   => $item->getDlrType(),
                'code'      => $item->getCode1(),
                'code2'     => $item->getCode2(),
                'code3'     => $item->getCode3(),
                'soldCode'  => $item->getSoldCode(),
                'shipCode'  => $item->getShipCode(),
                'abbreviatedName'  => $item->getAbbreviatedName(),
                'name'      => $item->getName(),
                'address'   => $item->getAddress(),
                'city'      => $item->getCity(),
                'state'     => $item->getState(),
                'zip'       => $item->getZip(),
                'phone'     => $item->getPhone(),
                'fax'       => $item->getFax(),
                'country'   => $item->getCountry(),
                'updatedAt' => $item->getUpdatedAt()->format("Y-m-d H:i"),
            ];
        }

        return new JsonResponse($return);
    }

    /**
     * @Route("/sync/{site}", name="sync_data")
     */
    public function syncAction($site){

        if($site != "adbuilder-dev" and $site != "adbuilder-prod" and $site != "tomahawk-dev" and $site != "tomahawk-prod" and $site != "gtd-dev" and $site != "gtd-prod" and $site != "gtd-staging"){
            throw $this->createNotFoundException('Impossible to sync with this target.');
        }

        $em = $this->getDoctrine()->getManager(str_replace("-", "_", $site));
        if(strpos($site, "tomahawk") !== false) $tableName = "Dealer";
        elseif(strpos($site, "adbuilder") !== false) $tableName = "dealers";
        else $tableName = "tsm_data";

        $env = explode("-",$site)[1];

        $tsms = $this->getDoctrine()->getRepository('AppBundle:Tsm')->findAll();
        $new = 0;
        $updated = 0;

        foreach($tsms as $item){

            if($item->getCountry() == "") continue;

            $code = $item->getCode();
            $masterCode = $item->getCode();
            $masterCode = substr($masterCode, 0, -1);
            if(strpos($item->getName(), "Titan") !== false) $masterCode = "TITAN";

            $stmt = $em->getConnection()->prepare("SELECT id FROM ".$tableName." WHERE `code` = :code");
            $stmt->bindValue(':code', $code);
            $stmt->execute();
            $res = $stmt->fetch();

            if(!empty($res['id'])){
                //update
                if($tableName == "tsm_data"){
                    $query = "UPDATE " . $tableName . " SET `tsm` = :territory_id, `name` = :name,
                    `address` = :address, `city` = :city, `state` = :state, `zip` = :zip, `county` = :county,
                    `country` = :country, `phone` = :phone, `fax` = :fax, `reg` = :reg, `rsd` = :rsd,
                    `tsm_name` = :tsm_name, `dlr_type` = :dlr_type, `code1` = :code1, `code2` = :code2,
                    `code3` = :code3, `sold_code` = :sold_code, `ship_code` = :ship_code,
                    `abbreviated_name` = :abbreviated_name, `updated_at` = NOW() WHERE id = " . $res['id'];
                    $stmt = $em->getConnection()->prepare($query);
                    $stmt->bindValue(':reg', $item->getReg());
                    $stmt->bindValue(':rsd', $item->getRsd());
                    $stmt->bindValue(':tsm_name', $item->getTsmName());
                    $stmt->bindValue(':dlr_type', $item->getDlrType());
                    $stmt->bindValue(':code1', $item->getCode1());
                    $stmt->bindValue(':code2', $item->getCode2());
                    $stmt->bindValue(':code3', $item->getCode3());
                    $stmt->bindValue(':sold_code', $item->getSoldCode());
                    $stmt->bindValue(':ship_code', $item->getShipCode());
                    $stmt->bindValue(':abbreviated_name', $item->getAbbreviatedName());
                }else {
                    $query = "UPDATE " . $tableName . " SET `territory_id` = :territory_id, `name` = :name,
                    `address` = :address, `city` = :city, `state` = :state, `zip` = :zip, `county` = :county,
                    `country` = :country, `phone` = :phone, `fax` = :fax, `updatedAt` = NOW() WHERE id = " . $res['id'];
                    $stmt = $em->getConnection()->prepare($query);
                }

                $stmt->bindValue(':territory_id', $item->getTsm());
                $stmt->bindValue(':name', $item->getName());
                $stmt->bindValue(':address', $item->getAddress());
                $stmt->bindValue(':city', $item->getCity());
                $stmt->bindValue(':state', $item->getState());
                $stmt->bindValue(':zip', $item->getZip());
                $stmt->bindValue(':county', $item->getCounty());
                $stmt->bindValue(':country', $item->getCountry());
                $stmt->bindValue(':phone', $item->getPhone());
                $stmt->bindValue(':fax', $item->getFax());

                $updated++;
            }else{
                //new
                if($tableName == "tsm_data"){
                    $query = "INSERT INTO " . $tableName . " (`tsm`, `code`, `name`, `address`, `city`, `state`, `zip`,
                    `county`, `country`, `phone`, `fax`, `reg`, `rsd`, `tsm_name`, `dlr_type`, `code1`, `code2`, `code3`,
                    `sold_code`, `ship_code`, `abbreviated_name`, `created_at`)
                    VALUES (:territory_id, :code, :name, :address, :city, :state, :zip, :county, :country, :phone, :fax,
                    :reg, :rsd, :tsm_name, :dlr_type, :code1, :code2, :code3, :sold_code, :ship_code, :abbreviated_name, NOW())
                    ";
                    $stmt = $em->getConnection()->prepare($query);
                    $stmt->bindValue(':reg', $item->getReg());
                    $stmt->bindValue(':rsd', $item->getRsd());
                    $stmt->bindValue(':tsm_name', $item->getTsmName());
                    $stmt->bindValue(':dlr_type', $item->getDlrType());
                    $stmt->bindValue(':code1', $item->getCode1());
                    $stmt->bindValue(':code2', $item->getCode2());
                    $stmt->bindValue(':code3', $item->getCode3());
                    $stmt->bindValue(':sold_code', $item->getSoldCode());
                    $stmt->bindValue(':ship_code', $item->getShipCode());
                    $stmt->bindValue(':abbreviated_name', $item->getAbbreviatedName());
                }else {
                    $query = "INSERT INTO " . $tableName . " (`territory_id`, `code`, `name`, `address`, `city`, `state`, `zip`,
                    `county`, `country`, `phone`, `fax`, `master_code`, `createdAt`)
                    VALUES (:territory_id, :code, :name, :address, :city, :state, :zip, :county, :country, :phone,
                    :fax, :master_code, NOW())
                    ";
                    $stmt = $em->getConnection()->prepare($query);
                    $stmt->bindValue(':master_code', $masterCode);
                }
                $stmt->bindValue(':territory_id', $item->getTsm());
                $stmt->bindValue(':code', $code);
                $stmt->bindValue(':name', $item->getName());
                $stmt->bindValue(':address', $item->getAddress());
                $stmt->bindValue(':city', $item->getCity());
                $stmt->bindValue(':state', $item->getState());
                $stmt->bindValue(':zip', $item->getZip());
                $stmt->bindValue(':county', $item->getCounty());
                $stmt->bindValue(':country', $item->getCountry());
                $stmt->bindValue(':phone', $item->getPhone());
                $stmt->bindValue(':fax', $item->getFax());

                $new++;
            }

            $stmt->execute();
        }

        //change last update date
        $now = new \DateTime();
        $em = $this->getDoctrine()->getManager();
        $lastUpdate = $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName($site);
        if($lastUpdate) {
            $lastUpdate->setLastUpdate($now);
            $em->flush();
        }

        return new JsonResponse(array(
            'msg' => "New - ".$new.". Updated - ".$updated,
            'lastUpdate' => $now->format("m/d/Y h:i:s a"),
        ));
    }

    /**
     * @Route("/sync", name="sync")
     */
    public function Action(Request $request){
        return $this->render('@App/Default/sync.html.twig', [
            'lastUpdate' => array(
                'adbuilderDev' => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("adbuilder-dev"),
                'adbuilderProd' => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("adbuilder-prod"),
                'tomahawkDev'  => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("tomahawk-dev"),
                'tomahawkProd'  => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("tomahawk-prod"),
                'gtdDev'  => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("gtd-dev"),
                'gtdStaging'  => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("gtd-staging"),
                'gtdProd'  => $this->getDoctrine()->getRepository('AppBundle:Sync')->findOneByName("gtd-prod"),
            )
        ]);
    }
}