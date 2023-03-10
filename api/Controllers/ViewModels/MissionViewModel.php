<?php


namespace Controllers\ViewModels;


use BusinessLogic\MissionType;
use DataAccess\Models\MapFloorToName;
use DataAccess\Models\Mission;
use DateTime;
use DateTimeInterface;

class MissionViewModel {
    public int $id;
    public int $locationId;
    public ?int $campaignId;
    public string $name;
    public string $slug;
    public int $order;
    public string $mapFolderName;
    public string $mapCenterLatitude;
    public string $mapCenterLongitude;
    public int $lowestFloorNumber;
    public int $highestFloorNumber;
    public int $startingFloorNumber;
    public string $topLeftCoordinate;
    public string $bottomRightCoordinate;
    public bool $satelliteView;
    public ?string $beginEffectiveDate;
    public ?string $endEffectiveDate;
    public string $missionType;
    public string $backgroundUrl;
    public string $tileUrl;
    public bool $svg;
    public int $minZoom;
    public int $maxZoom;
    public string $boundingBoxTopLeft;
    public string $boundingBoxBottomRight;
    // TODO ViewModel
    public array $floorNames = [];

    public function __construct(Mission $mission) {
        $this->id = $mission->getId();
        $this->locationId = $mission->getLocationId();
        $this->campaignId = $mission->getCampaignId();
        $this->name = $mission->getName();
        $this->slug = $mission->getSlug();
        $this->order = $mission->getOrder();
        $this->mapFolderName = $mission->getMapFolderName();
        $this->mapCenterLatitude = $mission->getMapCenterLatitude();
        $this->mapCenterLongitude = $mission->getMapCenterLongitude();
        $this->lowestFloorNumber = $mission->getLowestFloorNumber();
        $this->highestFloorNumber = $mission->getHighestFloorNumber();
        $this->startingFloorNumber = $mission->getStartingFloorNumber();
        $this->topLeftCoordinate = $mission->getTopLeftCoordinate();
        $this->bottomRightCoordinate = $mission->getBottomRightCoordinate();
        $this->satelliteView = $mission->getSatelliteView();
        $f = $mission->getBeginEffectiveDate();
        if ($f === null) {
            $this->beginEffectiveDate = null;
        } else {
            $this->beginEffectiveDate = $f->format(DateTimeInterface::ATOM);
        }
        $f = $mission->getEndEffectiveDate();
        if ($f === null) {
            $this->endEffectiveDate = null;
        } else {
            $this->endEffectiveDate = $f->format(DateTimeInterface::ATOM);
        }
        $this->missionType = $mission->getMissionType();
        $this->backgroundUrl = $mission->getBackgroundUrl();
        $this->tileUrl = $mission->getTileUrl();
        $this->svg = $mission->getSvg();
        $this->minZoom = $mission->getMinZoom();
        $this->maxZoom = $mission->getMaxZoom();
        $this->boundingBoxTopLeft = $mission->getBoundingBoxTopLeft();
        $this->boundingBoxBottomRight = $mission->getBoundingBoxBottomRight();
        $this->floorNames = array_map(fn(MapFloorToName $x) => new MissionFloorNameViewModel($x), $mission->getFloorNames()->toArray());
        usort($this->floorNames, fn(MissionFloorNameViewModel $x, MissionFloorNameViewModel $y) => $x->floorNumber - $y->floorNumber);
    }
}