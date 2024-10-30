<?php

/*
* Copyright (C) 2005-2023 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* https://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* encode polyline
*
* @author      Kim Jackson
* @copyright   (C) 2005-2023 University of Sydney
* @link        https://HeuristNetwork.org
* @version     3.1.0
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

// where numLevels and zoomFactor indicate how many
// different levels of magnification the polyline has
// and the change in magnification between those levels,
// verySmall indicates the length of a barely visible
// object at the highest zoom level, forceEndpoints
// indicates whether or not the endpoints should be
// visible at all zoom levels. forceEndpoints is
// optional with a default value of true. Probably
// should stay true regardless.


$numLevels = 21;
$zoomFactor = 3;
$verySmall = 0.00001;
$forceEndpoints = true;

for($i = 0; $i < $numLevels; $i++)
{
    $zoomLevelBreaks[$i] = $verySmall * pow($zoomFactor, $numLevels-$i-1);
}

function computeLevel($dd)
{
    global $verySmall, $zoomLevelBreaks;

    if($dd > $verySmall)
    {
        $lev = 0;
        while($dd < $zoomLevelBreaks[$lev])
        {
            $lev++;
        }
    }
    return $lev;
}

function dpEncode($points)
{
    global $verySmall;

    $maxDist = 0;
    $absMaxDist = 0;
    $dists = array();

    if(count($points) < 3)
    {
        return encodedPoints($points, $dists, $absMaxDist);
    }

    $stack[] = array(0, count($points)-1);
    while(!empty($stack))
    {
        $current = array_pop($stack);
        $maxDist = 0;
        for($i = $current[0]+1; $i < $current[1]; $i++)
        {
            $temp = distance($points[$i], $points[$current[0]], $points[$current[1]]);
            if($temp > $maxDist)
            {
                $maxDist = $temp;
                $maxLoc = $i;
                if($maxDist > $absMaxDist)
                {
                    $absMaxDist = $maxDist;
                }
            }
        }
        if($maxDist > $verySmall)
        {
            $dists[$maxLoc] = $maxDist;
            array_push($stack, array($current[0], $maxLoc));
            array_push($stack, array($maxLoc, $current[1]));
        }
    }

    return encodedPoints($points, $dists, $absMaxDist);
}

function encodedPoints($points, $dists, $absMaxDist){
    $encodedPoints = createEncodings($points, $dists);
    $encodedLevels = encodeLevels($points, $dists, $absMaxDist);
    $encodedPointsLiteral = str_replace('\\',"\\\\",$encodedPoints);

    return array($encodedPoints, $encodedLevels, $encodedPointsLiteral);
}

function distance($p0, $p1, $p2)
{
    if($p1[0] == $p2[0] && $p1[1] == $p2[1])
    {
        $out = sqrt(pow($p2[0]-$p0[0],2) + pow($p2[1]-$p0[1],2));
    }
    else
    {
        $u = (($p0[0]-$p1[0])*($p2[0]-$p1[0]) + ($p0[1]-$p1[1]) * ($p2[1]-$p1[1])) / (pow($p2[0]-$p1[0],2) + pow($p2[1]-$p1[1],2));
        if($u <= 0)
        {
            $out = sqrt(pow($p0[0] - $p1[0],2) + pow($p0[1] - $p1[1],2));
        }
        if($u >= 1)
        {
            $out = sqrt(pow($p0[0] - $p2[0],2) + pow($p0[1] - $p2[1],2));
        }
        if(0 < $u && $u < 1)
        {
            $out = sqrt(pow($p0[0]-$p1[0]-$u*($p2[0]-$p1[0]),2) + pow($p0[1]-$p1[1]-$u*($p2[1]-$p1[1]),2));
        }
    }
    return $out;
}

function encodeSignedNumber($num)
{
    $sgn_num = $num << 1;
    if ($num < 0)
    {
        $sgn_num = ~($sgn_num);
    }
    return encodeNumber($sgn_num);
}

function createEncodings($points, $dists)
{
    $encoded_points = "";
    $plat = 0;
    $plng = 0;
    for($i=0; $i<count($points); $i++)
    {
        if(isset($dists[$i]) || $i == 0 || $i == count($points)-1)
        {
            $point = $points[$i];
            $lat = $point[0];
            $lng = $point[1];
            $late5 = floor($lat * 1e5);
            $lnge5 = floor($lng * 1e5);
            $dlat = $late5 - $plat;
            $dlng = $lnge5 - $plng;
            $plat = $late5;
            $plng = $lnge5;
            $encoded_points .= encodeSignedNumber($dlat) . encodeSignedNumber($dlng);
        }
    }
    return $encoded_points;
}

function encodeLevels($points, $dists, $absMaxDist)
{
    global $numLevels, $forceEndpoints;
    $encoded_levels = "";

    if($forceEndpoints)
    {
        $encoded_levels .= encodeNumber($numLevels-1);
    }
    else
    {
        $encoded_levels .= encodeNumber($numLevels-computeLevel($absMaxDist)-1);
    }
    for($i=1; $i<count($points)-1; $i++)
    {
        if(isset($dists[$i]))
        {
            $encoded_levels .= encodeNumber($numLevels-computeLevel($dists[$i])-1);
        }
    }
    if($forceEndpoints)
    {
        $encoded_levels .= encodeNumber($numLevels -1);
    }
    else
    {
        $encoded_levels .= encodeNumber($numLevels-computeLevel($absMaxDist)-1);
    }
    return $encoded_levels;
}

function encodeNumber($num)
{
    $encodeString = "";
    while($num >= 0x20)
    {
        $nextValue = (0x20 | ($num & 0x1f)) + 63;
        $encodeString .= chr($nextValue);
        $num >>= 5;
    }
    $finalValue = $num + 63;
    $encodeString .= chr($finalValue);
    return $encodeString;
}
