<?php

/**
* Select link field type (pointer or relationship marker) and add it recordtype structure
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @designer    Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

define('PDIR','../../');//need for proper path to js and css
require_once dirname(__FILE__).'/../../hclient/framecontent/initPage.php';
?>
<script src='selectLinkField.js'></script>

<script type="text/javascript">

    // Callback function for initialization
    function onPageInit(success){
        if(success){
            SelectLinkField();
        }
    }
</script>

<style>
    .ft_col{
        display:inline-block;
        width:80%;
        vertical-align:top;
        padding-left:10px;
        min-width:280px;
    }
    .ft_col > h2{
        padding-bottom:1em;
    }
    .ft_col > label{
        color: black;
        /*#6A7C99;*/
        font-weight:bold;
        font-size: 13px;
        line-height: 25px;
        padding-top:1em;
        padding-left:0.5em;
    }
    .ft_notes{
        padding-left:2em;
        padding-bottom:1em;
    }
    .ft_preview{
        font-size:0.9em;
        margin-bottom:2em;
        margin-left:5ex;
    }
</style>
</head>

<body style="overflow:hidden">

    <div style="width:100%;height:90%;padding:10px 10px 10px 0;overflow:none">

        <div class="ft_col">

            <div class="ft_preview">
                <span class="small-header" style="min-width: 30px;vertical-align: super">From:</span>
                <div style="display:inline-block;">
                    <img alt src='<?php echo ICON_PLACEHOLDER;?>' id="source_rectype_img"
                        style="vertical-align:top;margin-left:10px;" />

                    <h3 id="source_rectype" class="truncate"
                        style="max-width:550px;display:inline-block;margin: 0px 10px;">&nbsp;</h3>
                </div>
                <div  id="source_rectype_desc" class="heurist-helper3" style="max-width:550px;margin-left:80px;"></div>
                <br>

                <span class="small-header" style="min-width: 30px;vertical-align: super">To:</span>
                <div id="target_rectype_div" style="display:inline-block;">
                    <img alt src='<?php echo ICON_PLACEHOLDER;?>' id="target_rectype_img"
                        style="vertical-align:top;margin-left:10px;" />

                    <h3 id="target_rectype" class="truncate"
                        style="max-width:550px;display:inline-block;margin:0px 10px;">&nbsp;</h3>
                </div>

                <select id="sel_target_rectype_id" style="margin-left: 25px;display:none"></select>
                <div  id="target_rectype_desc" class="heurist-helper3" style="max-width:550px;margin-left:80px;"></div>

            </div>
            <br>

            <!-- redundancy note: this functionality is repeated in selectFieldType.html/js -->

            <hr>

            <div>
                <div style="display:inline-block; line-height: 1.5">

                    <input type="radio" name="ft_type" id="t_resourse" value="resource" class="ft_selfield">
                    <label  class="ft_selfield" for="t_resourse">Record pointer</label>
                    <br>
                    <input type="radio" name="ft_type" id="t_relmarker" value="relmarker" class="ft_selfield">
                    <label class="ft_selfield" for="t_relmarker">Relationship marker</label>
                </div>
                <div style="display:inline-block;vertical-align: top;padding: 15px 20px; line-height: 1.5">
                    <a href="#" id="hint_more_info1">
                        <span class="ui-icon ui-icon-circle-b-info" style="display:inline-block;vertical-align:middle;padding-right:8px"></span>
                        Which one should I choose?</a>
                </div>
            </div>

            <hr>

            <div style="display:block">
                <input type="radio" name="t_sel_field" id="t_add_new_field" checked>
                <label  class="ft_selfield" id="lt_add_new_field" for="t_add_new_field">Add new field</label>
            </div>
            <div>
                <input type="radio"  name="t_sel_field" id="t_use_existing_field">
                <label  class="ft_selfield" id="lt_use_existing_field" for="t_use_existing_field">Add existing field</label>
                <select id="sel_resource_fields" class="ft_selfield"></select>
                <select id="sel_relmarker_fields" class="ft_selfield" style='background:red'></select>
            </div>
            <span class="heurist-helper1" style="display: inline-block;padding: 5px 0 0 20px;">Only pointers towards the selected target entity type are shown</span>

            <div style="text-align:right;width:100%;padding-top:2em;">
                <button type="button" id="btnSelect" disabled="disabled">
                    Create link
                </button>
            </div>
        </div>


    </div>

</body>
</html>