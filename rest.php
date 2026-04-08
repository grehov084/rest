<?
    require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
    require("src/crest.php");
    require("src/getCoords.php");

/*
    Доделки:
    1. Запрос координат по адресу для дилеров

*/
class Rest
{
    private $propMap = [];

    function __construct($iblockCrmId)
    {
        $resultProps = CRest::call(
            'lists.field.get',
            [
                'IBLOCK_ID' => $iblockCrmId,
                'IBLOCK_TYPE_ID' => 'lists'
            ]
        );
        $propMap = [];
        foreach ($resultProps['result'] as $arResultProps) {
            $propMap[$arResultProps['FIELD_ID']] = $arResultProps;
        }
        $this->propMap = $propMap;
    }

    function print($elem)
    {
        echo "<pre>";
        print_r($elem);
        echo "</pre>";
    }

    function agregateIBlock($arElems, $iblockId)
    {
        if (!CModule::IncludeModule("iblock")) {
            return;
        }
        $listElems = CIBlockElement::GetList(
            ["SORT" => "ASC"],
            ["IBLOCK_ID" => $iblockId],
            false,
            false,
            false
        );
        $arExistElems = [];
        while ($elem = $listElems->GetNextElement()) {
            $arItem = $elem->GetFields();
            $arItem["PROPERTIES"] = $elem->GetProperties();
            array_push($arExistElems, $arItem);
        }

        $allowAdding = true;
        $edit = false;
        $idForEdit = null;
        $arIdsForRemove = [];

        $intersect = array_intersect($arElems, $arExistElems);

        foreach ($arExistElems as $arExistElem) {
            $exist = false;
            foreach ($intersect as $arInter) {
                if ($arInter["ID"] == $arExistElem["PROPERTIES"]["CRM_ID"]["VALUE"]) {
                    $exist = true;
                    break;
                }
            }
            if (!$exist) {
                array_push($arIdsForRemove, $arExistElem["ID"]);
            }
        }

        foreach ($arIdsForRemove as $arId) {
            $el = new CIBlockElement;
            $arLoadProductArray = [
                "IBLOCK_SECTION_ID" => false,
                "IBLOCK_ID"         => $iblockId,
                "ACTIVE"            => "N",
                "LICENSE_POPUP"     => "Y",
            ];
            $el->Update($arId, $arLoadProductArray);
        }

        foreach ($arElems as $arElem) {
            $allowAdding = true;
            $edit = false;
            $idForEdit = null;
            foreach ($arExistElems as $arExistElem) {
                if ($arElem["ID"] == $arExistElem["PROPERTIES"]["CRM_ID"]["VALUE"]) {
                    $allowAdding = false;
                    $edit = true;
                    $idForEdit = $arExistElem["ID"];
                    break;
                } elseif ($arElem["CODE"] == $arExistElem["CODE"]) {
                    $allowAdding = false;
                    $edit = true;
                    $idForEdit = $arExistElem["ID"];
                    break;
                }
            }

            $el = new CIBlockElement;
            $PROP = [
                "PHONE"   => $arElem["PROPERTIES"]["PHONE"]["VALUE"],
                "EMAIL"   => $arElem["PROPERTIES"]["EMAIL"]["VALUE"],
                "SITE"    => $arElem["PROPERTIES"]["WEBSITE"]["VALUE"],
                "TG"      => $arElem["PROPERTIES"]["TELEGRAM"]["VALUE"],
                "VK"      => $arElem["PROPERTIES"]["VK"]["VALUE"],
                "ADDRESS" => $arElem["PROPERTIES"]["ADDRESS"]["VALUE"],
                "CRM_ID"  => $arElem["ID"],
            ];
            $arLoadProductArray = [
                "IBLOCK_ID"         => $iblockId,
                "PREVIEW_TEXT"      => $arElem["PROPERTIES"]["DESCRIPTION"]["VALUE"],
                "PROPERTY_VALUES"   => $PROP,
                "NAME"              => $arElem["NAME"],
                "CODE"              => $arElem["CODE"],
                "ACTIVE"            => "Y",
                "PREVIEW_PICTURE"   => CFile::MakeFileArray($arElem["PROPERTIES"]["PHOTO"]["VALUE"]),
                "DETAIL_PICTURE"    => CFile::MakeFileArray($arElem["PROPERTIES"]["PHOTO"]["VALUE"]),
                "LICENSE_POPUP"     => "Y",
            ];
            
            if ($allowAdding) {
                $iblockSectionId = false;
                if ($iblockId == 20) {
                    $sections = CIBlockSection::GetList(
                        [],
                        ["IBLOCK_ID" => $iblockId],
                        false
                    );
                    $iblockSectionId = null;
                    while ($res = $sections->GetNext()) {
                        if ($res["NAME"] == $arElem["PROPERTIES"]["CITY"]["VALUE"]) {
                            $iblockSectionId = $res["ID"];
                            break;
                        }
                    }
                    if ($iblockSectionId === null) {
                        $bs = new CIBlockSection;
                        $arFields = [
                            "ACTIVE"    => "Y",
                            "IBLOCK_ID" => $iblockId,
                            "NAME"      => $arElem["PROPERTIES"]["CITY"]["VALUE"],
                        ];
                        $iblockSectionId = $bs->Add($arFields);
                        $arLoadProductArray = [
                            "IBLOCK_SECTION_ID" => $iblockSectionId,
                        ];
                    }
                }
                $el->Add($arLoadProductArray);
            } elseif ($edit) {
                $el->Update($idForEdit, $arLoadProductArray);
            }
        }
    }

    function getElem($iblockCrmId, $crmIblockSectionId)
    {
        $result = CRest::call(
            'lists.element.get',
            [
                'IBLOCK_ID'      => $iblockCrmId,
                'IBLOCK_TYPE_ID' => 'lists',
                'FILTER'         => ["PROPERTY_187" => $crmIblockSectionId]
            ]
        );

        $arProps = [];
        foreach ($result['result'] as $key => $arItem) {
            foreach ($arItem as $keyProp => $arField) {
                if (mb_strpos($keyProp, 'PROPERTY_') === 0) {
                    $propCode = $this->propMap[$keyProp]['CODE'];
                    if ($this->propMap[$keyProp]['TYPE'] == 'F') {
                        $file = CRest::call(
                            'lists.element.get.file.url',
                            [
                                'IBLOCK_ID'      => $iblockCrmId,
                                'IBLOCK_TYPE_ID' => 'lists',
                                'ELEMENT_ID'     => $arItem['ID'],
                                'FIELD_ID'       => $this->propMap[$keyProp]['ID'],
                                'SEF_FOLDER'     => '/services/lists/',
                            ]
                        );
                        $file["result"][0] = str_replace(
                            "/services/lists/34/file/0/" . $arItem["ID"] . "/PROPERTY_194/",
                            "",
                            $file["result"][0]
                        );
                        $file["result"][0] = str_replace("/?ncc=y&download=y", "", $file["result"][0]);
                        $fileId = $file["result"][0];
                        $url = 'https://crm.refloor-nsk.ru/pub/api/file/getById/';
                        $params = [
                            'fileId' => $fileId,
                            'token'  => sha1($fileId . 'Refloor2025'),
                        ];
                        $resultFile = file_get_contents(
                            $url,
                            false,
                            stream_context_create([
                                'http' => [
                                    'method'  => 'POST',
                                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                                    'content' => http_build_query($params)
                                ]
                            ])
                        );
                        $arField["IMAGE_SRC"] = json_decode($resultFile)->data->fileData[1];
                        unset($resultFile);
                    }

                    foreach ($arField as $keyVal => $value) {
                        $arField["VALUE"] = str_replace(" ", "_", $value);
                    }

                    $arProps[$propCode] = $arField;
                    if (!isset($arProps["PHOTO"])) {
                        $arField["IMAGE_SRC"] = __DIR__ . "/src/img/empty_photo.jpg";
                        $arProps["PHOTO"]["VALUE"] = str_replace("/home/bitrix/www", "", $arField["IMAGE_SRC"]);
                    }

                    unset($result['result'][$key][$keyProp]);
                }
            }
            $result['result'][$key]['PROPERTIES'] = $arProps;
            $result['result'][$key]["CODE"] = CUtil::translit(
                $result['result'][$key]["NAME"],
                "ru",
                ["replace_space" => "-", "replace_other" => "-"]
            );
            $arProps = [];
        }
        return $result['result'];
    }

    function getElemsList($iblockCrmId, $filter, $targetIbId, $crmIblockSectionId)
    {
        $arElems = $this->getElem($iblockCrmId, $crmIblockSectionId);
        $this->agregateIBlock($arElems, $targetIbId);
    }

    /**
     * Статический метод для запуска синхронизации на основе входящих параметров.
     * Вызывается один раз при обращении к скрипту.
     */
    public static function handleRequest()
    {
        $iblockCrmId = 34; // ID инфоблока в CRM
        $targetIbId = null;

        if (isset($_REQUEST["id"])) {
            switch ($_REQUEST["id"]) {
                case 9743:
                    $targetIbId = 12;
                    break;
                case 9838:
                    $targetIbId = 14;
                    break;
                case 10879:
                    $targetIbId = 20;
                    break;
                default:
                    $targetIbId = null;
                    break;
            }
        }

        if ($targetIbId !== null) {
            $rest = new self($iblockCrmId);
            $rest->getElemsList($iblockCrmId, null, $targetIbId, $_REQUEST["id"]);
        }
    }
}

// Запуск обработки запроса
Rest::handleRequest();
?>
