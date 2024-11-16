<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="de">
    <head>
        <title><?php print htmlspecialchars($website_title_translated);?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <meta content="Hagen Peukert" name="author">
        <meta name="keywords" content="Heurist, Digital Humanities, Humanities Data, Research Data, Database Management, Academic data, Open Source, Free software, FOSS, University of Sydney,<?php echo $meta_keywords;?>">
        <meta name="description" content="<?php echo $meta_description;?>">

        <!-- 9 Oct 2019: The styles were commented out. Probably the stylesheets no longer contain
        styles for the homepage - to check - as the page is largely unstyled, but the button styles
        ARE used and work since removal of the comments - so why were these commented out? -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

        <link rel="alternate" hreflang="de" href="index.html">
        <link rel="alternate" hreflang="en" href="index_en.html">

        <!--style type="text/css">
            button {width:200px;}
            .buttons {display: table-cell; padding-right: 20px; text-align: left; vertical-align: middle; width: 250;}
            .description {width:400px; display:table-cell; text-align:left;vertical-align: middle; margin-left: 20px; margin-top:10px; margin-bottom:20px;}
            .options {display: table; margin: 0 auto; padding: 10px 0; text-align: left;}
            body {overflow-y:auto}
            #page {left:10%; right:10%}
            .small {width:13px;margin:0 auto}
        </style-->

        <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>

    <?php
        include_once $websiteScriptAndStyles_php;
    ?>
    <style>
body{
    overflow:auto;
}

#main-menu  ul.horizontalmenu>li>a{
    font-size: 18px;
    text-transform: uppercase;
    font-family: TheSansUHHBoldCaps;
}
#main-menu .ui-menu .ui-menu-item a {
    font-size: 18px;
    color: #333;
    font-family: TheSansUHHBold;
}
#main-menu .ui-menu .ui-menu-item a:hover,
#main-menu .ui-menu .ui-menu-item a.ui-state-active {
    color:#0271bb !important;
    background: none;
    border:none !important;
}
#main-menu ul.ui-widget-content>li{
    padding: 5px 0px !important;
}
#main-menu ul.ui-widget-content>li>ul {
    padding: 30px 70px 35px 50px !important;
    background-color: #fff;
    border: none;
}

.text{
    width:100% !important;
}

.text2{
    display: table-row !important;
    position: absolute !important;
    left: 300px !important;
    top: 65px !important;
    height: 80px;
}
#UHH_HEADER:not(.l) .text2{
    top: 44px !important;
    left: 150px !important;
}
.text2 a{
    position:relative !important;
}
.page {
    width: 100%;
    max-width: 1258px;
    margin: auto;
    overflow: hidden;
    min-height: 400px;
}

    </style>
    </head>

    <body>
        <div class="suche" id="uhh-header-search">
            <form action="https://www.fdm.uni-hamburg.de/search.html">
                <h6 class="versteckt">Suche</h6>
                <label for="suchfeld" class="versteckt">Suche nach </label>
                <input id="suchfeld" placeholder="Suche" name="q" class="text" type="text">
                <input name="suchen" class="button" alt="suchen" title="suchen" type="submit">
            </form>
        </div>

        <div id="uhh-header-sublogo">
            <!-- HEURIST CMS LOGO IMAGE -->
            <!--div class="text2">
                <div id="main-title" style="display: table-cell;width: 70%;"></div>
                <div id="main-title-alt" style="display: table-cell;"></div>
                <div id="main-title-alt2" style="display: table-cell;"></div>
                <div id="main-logo" style="width: 145px;display: table-cell"></div>
                <div id="main-logo-alt" style="display: table-cell;min-width: 140px;height: 80px;"></div>
            </div-->
<?php
    if(!$edit_OldEditor && $system->isMember(1)){
        print '<a href="'.HEURIST_BASE_URL.'?db='.$system->dbname().'" id="btn_editor" target="_blank" '
        .'style="position:absolute;left:120px; font-size:14px !important; top:5px;color:blue !important;" class="cms-button">database</a>'
        .'<a href="#" id="btnOpenCMSeditor" onclick="_openCMSeditor(event); return false;" '
        .'style="position:absolute;left:5px; font-size:14px !important; top:5px;color:blue !important;" class="cms-button">website editor</a>';
    }
    if(!$edit_OldEditor && $system->getUserId() == 0 && $show_login_button){
        print '<a href="#" id="btn_signin" style="position:absolute;right:5px; top:5px;color:blue !important;font-weight: normal;" class="cms-button">login</a>';// login link
    }
?>

          <div class="kurz"><a href="<?php print $image_altlogo_url;?>"><?php print $title_alt . " " . $title_alt2;?></a></div>
          <h1 class="wortmarke"><a href="<?php print $image_altlogo_url;?>"><b><?php print $title_alt;?></b><?php print $title_alt2;?></a></h1>

          <a href="#"></a><!-- do not touch this stub for UH script -->
        </div>

        <div id="uhh-header-nav">
          <!-- HEADER MENU -->
          <?php
          //output bootstrap menu
          if($mainmenu_content!=null){
                print $mainmenu_content;
          }
          ?>
          <div id="mobilemenu" style="display:none"></div>
        </div>

        <div class="page">
            <!-- HEURIST CMS PAGE CONTENT -->
            <div id="main-content"
                style="padding:0 10px;"
                data-homepageid="<?php print $home_page_record_id;?>"
                <?php print ($open_page_or_record_on_init>0)?' data-initid="'.$open_page_or_record_on_init.'"':'';?>
                data-viewonly="<?php print ($hasAccess)?0:1;?>">
            </div>
        </div>
        <div id="uhh-footer-info">
          <ul>
            <li><a href="https://www.fdm.uni-hamburg.de/imprint.html">Impressum</a></li>
            <li><a href="https://www.fdm.uni-hamburg.de/privacypolicy.html">Datenschutzerklärung</a></li>
          </ul>
        </div>
    </body>
    <script id="UHH-DOM" src="https://www.uni-hamburg.de/onTEAM/inc/dom/v5/insert.js" async
      data-options='{
        "header": {
          "language":{},
          "sublogo":{},
          "mobilemenu":{"dom":""},
          "nav":{},
          "search":{}
        },
        "footer": {
            "info":{}
    },
    "links": {
      "leichtesprache":"leichtesprache.html",
      "barrierefreiheit":"barrierefreiheit.html"
    }
      }'>
    </script>
</html>
