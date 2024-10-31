<?php

/**
 * PHP class to convert Latitude & Longitude coordinates into UTM & Lambert Conic Conformal Northing/Easting coordinates.
 *
 * This class encapsulates the methods for representing a geographic point on the earth in three different coordinate systema. Lat/Long, UTM and Lambert Conic Conformal.
 *
 * Code for datum and UTM conversion was converted from C++ code written by Chuck Gantz (chuck.gantz@globalstar.com) from http://www.gpsy.com/gpsinfo/geotoutm/
 * This code was converted into PHP by Brenor Brophy (brenor@sbcglobal.net) and later refactored for PHP 5.3 by Hans Duedal (hd@onlinecity.dk).
 *
 * @author chuck.gantz@globalstar.com, brenor@sbcglobal.net, hd@onlinecity.dk
 * @version 1.0
 *
 * COPYRIGHT (c) 2005, 2006, 2007, 2008 BRENOR BROPHY
 * The source code included in this package is free software; you can
 * redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation. This license can be read at:
 *
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * @link http://www.gpsy.com/gpsinfo/geotoutm/
 * @link http://www.phpclasses.org/browse/file/10671.html
 * @link https://gist.github.com/840476#file_gpointconverter.class.php
 */
define('WGS_84','WGS 84');

class GpointConverter
{

    /**
     * Reference ellipsoids derived from Peter H. Dana's website:
     *     http://www.utexas.edu/depts/grg/gcraft/notes/datum/elist.html
     *     Department of Geography, University of Texas at Austin
     *     Internet: pdana@mail.utexas.edu 3/22/95
     * Source:
     *     Defense Mapping Agency. 1987b. DMA Technical Report: Supplement to Department of Defense World Geodetic System 1984 Technical Report. Part I and II.
     *     Washington, DC: Defense Mapping Agency
     * Alternative names added in for easy compatibility by hd@onlinecity.dk
     *
     * @var array - format ("Ellipsoid name" => array(Equatorial Radius, square of eccentricity))
     */
    public static $ellipsoid = array(
        "Airy"                    =>array (6377563, 0.00667054),
        "Australian National"    =>array    (6378160, 0.006694542),
        "Bessel 1841"            =>array    (6377397, 0.006674372),
        "Bessel 1841 Nambia"    =>array    (6377484, 0.006674372),
        "Clarke 1866"            =>array    (6378206, 0.006768658),
        "Clarke 1880"            =>array    (6378249, 0.006803511),
        "Everest"                =>array    (6377276, 0.006637847),
        "Fischer 1960 Mercury"    =>array (6378166, 0.006693422),
        "Fischer 1968"            =>array (6378150, 0.006693422),
        "GRS 1967"                =>array    (6378160, 0.006694605),
        "GRS 1980"                =>array    (6378137, 0.00669438),
        "Helmert 1906"            =>array    (6378200, 0.006693422),
        "Hough"                    =>array    (6378270, 0.00672267),
        "International"            =>array    (6378388, 0.00672267),
        "Krassovsky"            =>array    (6378245, 0.006693422),
        "Modified Airy"            =>array    (6377340, 0.00667054),
        "Modified Everest"        =>array    (6377304, 0.006637847),
        "Modified Fischer 1960"    =>array    (6378155, 0.006693422),
        "South American 1969"    =>array    (6378160, 0.006694542),
        "WGS 60"                =>array (6378165, 0.006693422),
        "WGS 66"                =>array (6378145, 0.006694542),
        "WGS 72"                =>array (6378135, 0.006694318),
        "WGS 84"                =>array (6378137, 0.00669438),

        // Alternative names, added in for easy compatibility by hd@onlinecity.dk
        "ED50"                    =>array    (6378388, 0.00672267), // International Ellipsoid
        "EUREF89"                =>array    (6378137, 0.00669438), // Max deviation from WGS 84 is 40 cm/km see (in danish) http://www2.kms.dk/C1256AED004E87BA/(AllDocsByDocId)/3382517647F695C9C1256BC700265CE7
        "ETRS89"                =>array    (6378137, 0.00669438)  // Same as EUREF89
    );

    // Properties
    protected $a;                                    // Equatorial Radius
    protected $e2;                                    // Square of eccentricity
    protected $datum;                                // Selected datum
    protected $Xp;
    protected $Yp;                                // X,Y pixel location
    protected $lat;
    protected $long;                            // Latitude & Longitude of the point
    protected $utmNorthing;
    protected $utmEasting;
    protected $utmZone;    // UTM Coordinates of the point
    protected $lccNorthing;
    protected $lccEasting;            // Lambert coordinates of the point
    protected $falseNorthing;
    protected $falseEasting;        // Origin coordinates for Lambert Projection
    protected $latOfOrigin;                            // For Lambert Projection
    protected $longOfOrigin;                        // For Lambert Projection
    protected $firstStdParallel;                    // For lambert Projection
    protected $secondStdParallel;                    // For lambert Projection

    /**
     * Constructs the object and sets the datum
     *
     * @param string $datum
     */
    public function __construct($datum=WGS_84)            // Default datum is WGS 84
    {
        $this->a = self::$ellipsoid[$datum][0];// Set datum Equatorial Radius
        $this->e2 = self::$ellipsoid[$datum][1];// Set datum Square of eccentricity
        $this->datum = $datum;                        // Save the datum
    }

    /**
     * Set the datum
     *
     * @param string $datum
     */
    public function setDatum($datum=WGS_84)
    {
        $this->a = self::$ellipsoid[$datum][0];// Set datum Equatorial Radius
        $this->e2 = self::$ellipsoid[$datum][1];// Set datum Square of eccentricity
        $this->datum = $datum;                        // Save the datum
    }

    /**
     * Set X & Y pixel of the point (used if it is being drawn on an image)
     *
     * @param integer $x
     * @param integer $y
     */
    public function setXY($x, $y)
    {
        $this->Xp = $x; $this->Yp = $y;
    }

    /**
     * Get X pixel location
     */
    public function Xp() {
        return $this->Xp;
    }

    /**
     * Get Y pixel location
     */
    public function Yp() {
        return $this->Yp;
    }

    /**
     * Set/Get/Output Longitude & Latitude of the point
     * @param float $long
     * @param float $lat
     */
    public function setLongLat($long, $lat)
    {
        $this->long = $long;
        $this->lat = $lat;
    }

    /**
     * Get latitude
     *
     */
    public function Lat() {
        return $this->lat;
    }

    /**
     * Get longitude
     *
     */
    public function Long() {
        return $this->long;
    }

    /**
     * Print latitude/longitude
     *
     */
    public function printLatLong() {
        printf("Latitude: %1.5f Longitude: %1.5f",$this->lat, $this->long);
    }


    /**
     * Set Universal Transverse Mercator Coordinates
     *
     * @param integer $easting
     * @param integer $northing
     * @param string $zone
     */
    public function setUTM($easting, $northing, $zone='')    // Zone is optional
    {
        $this->utmNorthing = $northing;
        $this->utmEasting = $easting;
        if($zone!=null && $zone!='') {$this->utmZone = $zone;}
    }

    public function setUTMZone($zone){
        $this->utmZone = $zone;
    }


    /**
     * Get utm northing
     *
     */
    public function N() {
        return $this->utmNorthing;
    }

    /**
     * Get utm easting
     *
     */
    public function E() {
        return $this->utmEasting;
    }

    /**
     * Get utm zone
     */
    public function Z() {
        return $this->utmZone;
    }

    /**
     * Print UTM coordinates
     *
     */
    public function printUTM() {
        print "Northing: ".(int)$this->utmNorthing.", Easting: ".(int)$this->utmEasting.", Zone: ".$this->utmZone;
    }

    /**
     * Set the lambert coordinates
     *
     * @param integer $northing
     * @param integer $easting
     */
    public function setLambert($northing, $easting)
    {
        $this->lccNorthing = $northing;
        $this->lccEasting = $easting;
    }

    /**
     * Get lccNorthing
     */
    public function lccN() {
        return $this->lccNorthing;
    }

    /**
     * Get lccEasting
     */
    public function lccE() {
        return $this->lccEasting;
    }

    /**
     * Print lambert coordinates
     *
     */
    public function printLambert() {
        print  "Northing: ".(int)$this->lccNorthing.", Easting: ".(int)$this->lccEasting;
    }

    /**
     * Convert Longitude/Latitude to UTM
     *
     * Equations from USGS Bulletin 1532
     * East Longitudes are positive, West longitudes are negative.
     * North latitudes are positive, South latitudes are negative
     * Lat and Long are in decimal degrees
     * Written by Chuck Gantz- chuck.gantz@globalstar.com, converted to PHP by
     * Brenor Brophy, brenor@sbcglobal.net
     *
     * UTM coordinates are useful when dealing with paper maps. Basically the
     * map will can cover a single UTM zone which is 6 degrees on longitude.
     * So you really don't care about an object crossing two zones. You just get a
     * second map of the other zone. However, if you happen to live in a place that
     * straddles two zones (For example the Santa Babara area in CA straddles zone 10
     * and zone 11) Then it can become a real pain having to have two maps all the time.
     * So relatively small parts of the world (like say California) creat their own
     * version of UTM coordinates that are adjusted to conver the whole area of interest
     * on a single map. These are called state grids. The projection system is the
     * usually same as UTM (i.e. Transverse Mercator), but the central meridian
     * aka Longitude of Origin is selected to suit the logitude of the area being
     * mapped (like being moved to the central meridian of the area) and the grid
     * may cover more than the 6 degrees of longitude found on a UTM map. Areas
     * that are wide rather than long - think Montana as an example. May still
     * have to have a couple of maps to cover the whole state because TM projection
     * looses accuracy as you move further away from the Longitude of Origin, 15 degrees
     * is usually the limit.
     *
     * Now, in the case where we want to generate electronic maps that may be
     * placed pretty much anywhere on the globe we really don't to deal with the
     * issue of UTM zones in our coordinate system. We would really just like a
     * grid that is fully contigious over the area of the map we are drawing. Similiar
     * to the state grid, but local to the area we are interested in. I call this
     * Local Transverse Mercator and I have modified the function below to also
     * make this conversion. If you pass a Longitude value to the function as $LongOrigin
     * then that is the Longitude of Origin that will be used for the projection.
     * Easting coordinates will be returned (in meters) relative to that line of
     * longitude - So an Easting coordinate for a point located East of the longitude
     * of origin will be a positive value in meters, an Easting coordinate for a point
     * West of the longitude of Origin will have a negative value in meters. Northings
     * will always be returned in meters from the equator same as the UTM system. The
     * UTMZone value will be valid for Long/Lat given - thought it is not meaningful
     * in the context of Local TM. If a NULL value is passed for $LongOrigin
     * then the standard UTM coordinates are calculated.
     *
     * @param float $LongOrigin
     */
    public function convertLLtoTM($LongOrigin = null)
    {
        // Constants for UTM conversion
        $k0 = 0.9996;
        $falseEasting = 500000.0; // Standard UTM false easting value

        // Normalize the longitude to be within the range -180 to 179.9
        $LongTemp = fmod(($this->long + 180), 360) - 180;
        $LatRad = deg2rad($this->lat);
        $LongRad = deg2rad($LongTemp);

        // If no LongOrigin is provided, calculate it based on UTM zone
        if ($LongOrigin === null) {
            $ZoneNumber = $this->getZoneNumber($LongTemp);

            // Calculate longitude origin for the UTM zone
            $LongOrigin = ($ZoneNumber - 1) * 6 - 180 + 3;
            $this->utmZone = sprintf("%d%s", $ZoneNumber, $this->UTMLetterDesignator());
        }

        // Convert origin longitude to radians
        $LongOriginRad = deg2rad($LongOrigin);

        // Ellipsoid constants
        $eccPrimeSquared = $this->e2 / (1 - $this->e2);

        // Calculate the various terms for the projection
        $N = $this->a / sqrt(1 - $this->e2 * sin($LatRad) * sin($LatRad)); // Radius of curvature
        $T = tan($LatRad) * tan($LatRad); // Square of the tangent of latitude
        $C = $eccPrimeSquared * cos($LatRad) * cos($LatRad); // Second term of the projection formula
        $A = cos($LatRad) * ($LongRad - $LongOriginRad); // Difference in longitude

        // Calculate the meridional arc length (distance along the central meridian)
        $M = $this->a * (
            (1 - $this->e2 / 4 - 3 * $this->e2 * $this->e2 / 64 - 5 * $this->e2 * $this->e2 * $this->e2 / 256) * $LatRad
            - (3 * $this->e2 / 8 + 3 * $this->e2 * $this->e2 / 32 + 45 * $this->e2 * $this->e2 * $this->e2 / 1024) * sin(2 * $LatRad)
            + (15 * $this->e2 * $this->e2 / 256 + 45 * $this->e2 * $this->e2 * $this->e2 / 1024) * sin(4 * $LatRad)
            - (35 * $this->e2 * $this->e2 * $this->e2 / 3072) * sin(6 * $LatRad)
        );

        // Calculate UTM easting
        $this->utmEasting = ($k0 * $N * ($A + (1 - $T + $C) * $A * $A * $A / 6
            + (5 - 18 * $T + $T * $T + 72 * $C - 58 * $eccPrimeSquared) * $A * $A * $A * $A * $A / 120)
            + $falseEasting);

        // Calculate UTM northing
        $this->utmNorthing = ($k0 * ($M + $N * tan($LatRad) * ($A * $A / 2
            + (5 - $T + 9 * $C + 4 * $C * $C) * $A * $A * $A * $A / 24
            + (61 - 58 * $T + $T * $T + 600 * $C - 330 * $eccPrimeSquared) * $A * $A * $A * $A * $A * $A / 720)));

        // If the latitude is south of the equator, adjust the northing value
        if ($this->lat < 0) {
            $this->utmNorthing += 10000000.0; // Southern hemisphere offset
        }
    }

    private function getZoneNumber($LongTemp){

            $ZoneNumber = (int)(($LongTemp + 180) / 6) + 1;

            // Special case for Norway and Svalbard regions
            if ($this->lat >= 56.0 && $this->lat < 64.0 && $LongTemp >= 3.0 && $LongTemp < 12.0) {
                $ZoneNumber = 32;
            }elseif ($this->lat >= 72.0 && $this->lat < 84.0) {

                if ($LongTemp >= 0.0 && $LongTemp < 9.0) {
                    $ZoneNumber = 31;
                } elseif ($LongTemp >= 9.0 && $LongTemp < 21.0) {
                    $ZoneNumber = 33;
                } elseif ($LongTemp >= 21.0 && $LongTemp < 33.0) {
                    $ZoneNumber = 35;
                } elseif ($LongTemp >= 33.0 && $LongTemp < 42.0) {
                    $ZoneNumber = 37;
                }
            }

            return $ZoneNumber;
    }


    /**
     * This routine determines the correct UTM letter designator for the given latitude
     * returns 'Z' if latitude is outside the UTM limits of 84N to 80S
     * Written by Chuck Gantz- chuck.gantz@globalstar.com, converted to PHP by Brenor Brophy, brenor@sbcglobal.net
     */
    public function UTMLetterDesignator()
    {
        if((84 >= $this->lat) && ($this->lat >= 72)) {$LetterDesignator = 'X';}
        elseif((72 > $this->lat) && ($this->lat >= 64)) {$LetterDesignator = 'W';}
        elseif((64 > $this->lat) && ($this->lat >= 56)) {$LetterDesignator = 'V';}
        elseif((56 > $this->lat) && ($this->lat >= 48)) {$LetterDesignator = 'U';}
        elseif((48 > $this->lat) && ($this->lat >= 40)) {$LetterDesignator = 'T';}
        elseif((40 > $this->lat) && ($this->lat >= 32)) {$LetterDesignator = 'S';}
        elseif((32 > $this->lat) && ($this->lat >= 24)) {$LetterDesignator = 'R';}
        elseif((24 > $this->lat) && ($this->lat >= 16)) {$LetterDesignator = 'Q';}
        elseif((16 > $this->lat) && ($this->lat >= 8)) {$LetterDesignator = 'P';}
        elseif(( 8 > $this->lat) && ($this->lat >= 0)) {$LetterDesignator = 'N';}
        elseif(( 0 > $this->lat) && ($this->lat >= -8)) {$LetterDesignator = 'M';}
        elseif((-8 > $this->lat) && ($this->lat >= -16)) {$LetterDesignator = 'L';}
        elseif((-16 > $this->lat) && ($this->lat >= -24)) {$LetterDesignator = 'K';}
        elseif((-24 > $this->lat) && ($this->lat >= -32)) {$LetterDesignator = 'J';}
        elseif((-32 > $this->lat) && ($this->lat >= -40)) {$LetterDesignator = 'H';}
        elseif((-40 > $this->lat) && ($this->lat >= -48)) {$LetterDesignator = 'G';}
        elseif((-48 > $this->lat) && ($this->lat >= -56)) {$LetterDesignator = 'F';}
        elseif((-56 > $this->lat) && ($this->lat >= -64)) {$LetterDesignator = 'E';}
        elseif((-64 > $this->lat) && ($this->lat >= -72)) {$LetterDesignator = 'D';}
        elseif((-72 > $this->lat) && ($this->lat >= -80)) {$LetterDesignator = 'C';}
        else {$LetterDesignator = 'Z';}//This is here as an error flag to show that the Latitude is outside the UTM limits

        return $LetterDesignator;
    }

    /**
     * Convert UTM to Longitude/Latitude
     *
     * Equations from USGS Bulletin 1532
     * East Longitudes are positive, West longitudes are negative.
     * North latitudes are positive, South latitudes are negative
     * Lat and Long are in decimal degrees.
     * Written by Chuck Gantz- chuck.gantz@globalstar.com, converted to PHP by
     * Brenor Brophy, brenor@sbcglobal.net
     *
     * If a value is passed for $LongOrigin the the function assumes that
     * a Local (to the Longitude of Origin passed in) Transverse Mercator
     * coordinates is to be converted - not a UTM coordinate. This is the
     * complementary function to the previous one. The function cannot
     * tell if a set of LOCALNorthing/Easting coordinates are in the North
     * or South hemesphere - they just give distance from the equator not
     * direction - so only northern hemesphere lat/long coordinates are returned.
     * If you live south of the equator there is a note later in the code
     * explaining how to have it just return southern hemesphere lat/longs.
     *
     * @param float $LongOrigin
     */
    public function convertTMtoLL($LongOrigin=null)
    {
        $k0 = 0.9996;
        $e1 = (1-sqrt(1-$this->e2))/(1+sqrt(1-$this->e2));
        $falseEasting = 0.0;
        $y = $this->utmNorthing;

        if (!$LongOrigin) { // It is a UTM coordinate we want to convert
            sscanf($this->utmZone,"%d%s",$ZoneNumber,$ZoneLetter);
            if(strtoupper($ZoneLetter) != 'S') {
                $NorthernHemisphere = 1;//point is in northern hemisphere
            } else {
                $NorthernHemisphere = 0;//point is in southern hemisphere
                $y -= 10000000.0;//remove 10,000,000 meter offset used for southern hemisphere
            }
            $LongOrigin = ($ZoneNumber - 1)*6 - 180 + 3;  //+3 puts origin in middle of zone
            $falseEasting = 500000.0;
        }

//        $y -= 10000000.0;    // Uncomment line to make LOCAL coordinates return southern hemesphere Lat/Long
        $x = $this->utmEasting - $falseEasting; //remove 500,000 meter offset for longitude

        $eccPrimeSquared = ($this->e2)/(1-$this->e2);

        $M = $y / $k0;
        $mu = $M/($this->a*(1-$this->e2/4-3*$this->e2*$this->e2/64-5*$this->e2*$this->e2*$this->e2/256));

        $phi1Rad = $mu    + (3*$e1/2-27*$e1*$e1*$e1/32)*sin(2*$mu)
                    + (21*$e1*$e1/16-55*$e1*$e1*$e1*$e1/32)*sin(4*$mu)
                    +(151*$e1*$e1*$e1/96)*sin(6*$mu);
        $phi1 = rad2deg($phi1Rad);

        $N1 = $this->a/sqrt(1-$this->e2*sin($phi1Rad)*sin($phi1Rad));
        $T1 = tan($phi1Rad)*tan($phi1Rad);
        $C1 = $eccPrimeSquared*cos($phi1Rad)*cos($phi1Rad);
        $R1 = $this->a*(1-$this->e2)/pow(1-$this->e2*sin($phi1Rad)*sin($phi1Rad), 1.5);
        $D = $x/($N1*$k0);

        $tlat = $phi1Rad - ($N1*tan($phi1Rad)/$R1)*($D*$D/2-(5+3*$T1+10*$C1-4*$C1*$C1-9*$eccPrimeSquared)*$D*$D*$D*$D/24
                        +(61+90*$T1+298*$C1+45*$T1*$T1-252*$eccPrimeSquared-3*$C1*$C1)*$D*$D*$D*$D*$D*$D/720);
        $this->lat = rad2deg($tlat);

        $tlong = ($D-(1+2*$T1+$C1)*$D*$D*$D/6+(5-2*$C1+28*$T1-3*$C1*$C1+8*$eccPrimeSquared+24*$T1*$T1)
                        *$D*$D*$D*$D*$D/120)/cos($phi1Rad);
        $this->long = $LongOrigin + rad2deg($tlong);
    }

    /**
     * Configure a Lambert Conic Conformal Projection
     *
     * falseEasting & falseNorthing are just an offset in meters added to the final
     * coordinate calculated.
     *
     * longOfOrigin & LatOfOrigin are the "center" latitiude and longitude of the
     * area being projected. All coordinates will be calculated in meters relative
     * to this point on the earth.
     *
     * firstStdParallel & secondStdParallel are the two lines of longitude (that
     * is they run east-west) that define where the "cone" intersects the earth.
     * Simply put they should bracket the area being projected.
     *
     * @param integer $falseEasting
     * @param integer $falseNorthing
     * @param float $longOfOrigin
     * @param float $latOfOrigin
     * @param float $firstStdParallel
     * @param float $secondStdParallel
     */
    public function configLambertProjection ($falseEasting, $falseNorthing, $longOfOrigin, $latOfOrigin, $firstStdParallel, $secondStdParallel)
    {
        $this->falseEasting = $falseEasting;
        $this->falseNorthing = $falseNorthing;
        $this->longOfOrigin = $longOfOrigin;
        $this->latOfOrigin = $latOfOrigin;
        $this->firstStdParallel = $firstStdParallel;
        $this->secondStdParallel = $secondStdParallel;
    }

    /**
     * Convert Longitude/Latitude to Lambert Conic Easting/Northing
     *
     * This routine will convert a Latitude/Longitude coordinate to an Northing/
     * Easting coordinate on a Lambert Conic Projection. The configLambertProjection()
     * function should have been called prior to this one to setup the specific
     * parameters for the projection. The Northing/Easting parameters calculated are
     * in meters (because the datum used is in meters) and are relative to the
     * falseNorthing/falseEasting coordinate. Which in turn is relative to the
     * Lat/Long of origin The formula were obtained from URL:
     * http://www.ihsenergy.com/epsg/guid7_2.html.
     * Code was written by Brenor Brophy, brenor@sbcglobal.net
     *
     */
    public function convertLLtoLCC()
    {
        $e = sqrt($this->e2);

        $phi     = deg2rad($this->lat);// Latitude to convert
        $phi1    = deg2rad($this->firstStdParallel);// Latitude of 1st std parallel
        $phi2    = deg2rad($this->secondStdParallel);// Latitude of 2nd std parallel
        $lamda    = deg2rad($this->long);// Lonitude to convert
        $phio    = deg2rad($this->latOfOrigin);// Latitude of  Origin
        $lamdao    = deg2rad($this->longOfOrigin);// Longitude of  Origin

        $m1 = cos($phi1) / sqrt(( 1 - $this->e2*sin($phi1)*sin($phi1)));
        $m2 = cos($phi2) / sqrt(( 1 - $this->e2*sin($phi2)*sin($phi2)));
        $t1 = tan((pi()/4)-($phi1/2)) / pow(( ( 1 - $e*sin($phi1) ) / ( 1 + $e*sin($phi1) )),$e/2);
        $t2 = tan((pi()/4)-($phi2/2)) / pow(( ( 1 - $e*sin($phi2) ) / ( 1 + $e*sin($phi2) )),$e/2);
        $to = tan((pi()/4)-($phio/2)) / pow(( ( 1 - $e*sin($phio) ) / ( 1 + $e*sin($phio) )),$e/2);
        $t  = tan((pi()/4)-($phi /2)) / pow(( ( 1 - $e*sin($phi ) ) / ( 1 + $e*sin($phi ) )),$e/2);
        $n    = (log($m1)-log($m2)) / (log($t1)-log($t2));
        $F    = $m1/($n*pow($t1,$n));
        $rf    = $this->a*$F*pow($to,$n);
        $r    = $this->a*$F*pow($t,$n);
        $theta = $n*($lamda - $lamdao);

        $this->lccEasting = $this->falseEasting + $r*sin($theta);
        $this->lccNorthing = $this->falseNorthing + $rf - $r*cos($theta);
    }

    /**
     * Convert Easting/Northing on a Lambert Conic projection to Longitude/Latitude
     *
     * This routine will convert a Lambert Northing/Easting coordinate to an
     * Latitude/Longitude coordinate.  The configLambertProjection() function should
     * have been called prior to this one to setup the specific parameters for the
     * projection. The Northing/Easting parameters are in meters (because the datum
     * used is in meters) and are relative to the falseNorthing/falseEasting
     * coordinate. Which in turn is relative to the Lat/Long of origin The formula
     * were obtained from URL http://www.ihsenergy.com/epsg/guid7_2.html. Code
     * was written by Brenor Brophy, brenor@sbcglobal.net
     */
    public function convertLCCtoLL()
    {
        $e = sqrt($e2);

        $phi1    = deg2rad($this->firstStdParallel);// Latitude of 1st std parallel
        $phi2    = deg2rad($this->secondStdParallel);// Latitude of 2nd std parallel
        $phio    = deg2rad($this->latOfOrigin);// Latitude of  Origin
        $lamdao    = deg2rad($this->longOfOrigin);// Longitude of  Origin
        $E        = $this->lccEasting;
        $N        = $this->lccNorthing;
        $Ef        = $this->falseEasting;
        $Nf        = $this->falseNorthing;

        $m1 = cos($phi1) / sqrt(( 1 - $this->e2*sin($phi1)*sin($phi1)));
        $m2 = cos($phi2) / sqrt(( 1 - $this->e2*sin($phi2)*sin($phi2)));
        $t1 = tan((pi()/4)-($phi1/2)) / pow(( ( 1 - $e*sin($phi1) ) / ( 1 + $e*sin($phi1) )),$e/2);
        $t2 = tan((pi()/4)-($phi2/2)) / pow(( ( 1 - $e*sin($phi2) ) / ( 1 + $e*sin($phi2) )),$e/2);
        $to = tan((pi()/4)-($phio/2)) / pow(( ( 1 - $e*sin($phio) ) / ( 1 + $e*sin($phio) )),$e/2);
        $n    = (log($m1)-log($m2)) / (log($t1)-log($t2));
        $F    = $m1/($n*pow($t1,$n));
        $rf    = $this->a*$F*pow($to,$n);
        $r_    = sqrt( pow(($E-$Ef),2) + pow(($rf-($N-$Nf)),2) );
        $t_    = pow($r_/($this->a*$F),(1/$n));
        $theta_ = atan(($E-$Ef)/($rf-($N-$Nf)));

        $lamda    = $theta_/$n + $lamdao;
        $phi0    = (pi()/2) - 2*atan($t_);
        $phi1    = (pi()/2) - 2*atan($t_*pow(((1-$e*sin($phi0))/(1+$e*sin(phi0))),$e/2));
        $phi2    = (pi()/2) - 2*atan($t_*pow(((1-$e*sin($phi1))/(1+$e*sin(phi1))),$e/2));
        $phi    = (pi()/2) - 2*atan($t_*pow(((1-$e*sin($phi2))/(1+$e*sin(phi2))),$e/2));

        $this->lat     = rad2deg($phi);
        $this->long = rad2deg($lamda);
    }


    /**
     * This is a useful function that returns the Great Circle distance from the GpointConverter to another Long/Lat coordinate
     *
     * Result is returned as meters
     * @param float $lon1
     * @param float $lat1
     */
    public function distanceFrom($lon1, $lat1)
    {
        $lon1 = deg2rad($lon1); $lat1 = deg2rad($lat1);
        $lon2 = deg2rad($this->Long()); $lat2 = deg2rad($this->Lat());

        $theta = $lon2 - $lon1;
        $dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));

//        Alternative formula supposed to be more accurate for short distances
//        $dist = 2*asin(sqrt( pow(sin(($lat1-$lat2)/2),2) + cos($lat1)*cos($lat2)*pow(sin(($lon1-$lon2)/2),2)));
        return $dist * 6366710;// from http://williams.best.vwh.net/avform.htm#GCF
    }


    /**
     * This function also calculates the distance between two points. In this case it just uses Pythagoras's theorm using TM coordinates.
     * @param GpointConverter $pt
     */
    public function distanceFromTM(&$pt)
    {
        $E1 = $pt->E(); $N1 = $pt->N();
        $E2 = $this->E(); $N2 = $this->N();

        $dist = sqrt(pow(($E1-$E2),2)+pow(($N1-$N2),2));
        return $dist;
    }

    /**
     * This function geo-references a gePoint to a given map. This means that it
     * calculates the x,y pixel coordinate that coresponds to the Lat/Long value of
     * the geoPoint. The calculation is done using the Transverse Mercator(TM)
     * coordinates of the GpointConverter with respect to the TM coordinates of the center
     * point of the map. So this only makes sense if you are using Local TM
     * projection.
     *
     * $rX & $rY are the pixel coordinates that correspond to the Northing/Easting
     * ($rE/$rN) coordinate it is to this coordinate that he point will be
     * geo-referenced. The $LongOrigin is needed to make sure the Easting/Northing
     * coordinates of the point are correctly converted.
     *
     * @param integer $rX
     * @param integer $rY
     * @param integer $rE
     * @param integer $rN
     * @param integer $Scale
     * @param float $LongOrigin
     */
    public function gRef($rX, $rY, $rE, $rN, $Scale, $LongOrigin)
    {
        $this->convertLLtoTM($LongOrigin);
        $x = (($this->E() - $rE) / $Scale)        // The easting in meters times the scale to get pixels
                                                // is relative to the center of the image so adjust to
            + ($rX); // the left coordinate.
        $y = $rY -                              // Adjust to bottom coordinate.
            (($rN - $this->N()) / $Scale);// The northing in meters
                                                // relative to the equator. Subtract center point northing
                                                // to get relative to image center and convert meters to pixels
        $this->setXY((int)$x,(int)$y);// Save the geo-referenced result.
    }
}
?>