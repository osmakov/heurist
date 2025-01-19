/**
* cmsStatistics.js
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget( "heurist.cmsStatistics", $.heurist.baseAction, {

    
    _website_id: 0,
    
    // default options
    options: {
        actionName: 'cmsStatistics',
        default_palette_class: 'ui-heurist-publish',
        path: 'widgets/cms/',
    },

    
    _init: function() {
        
        if(!window.hWin.HAPI4.sysinfo.matomo_api_key){
            this.element.text('Matomo credentials are not defined');
        }else if(this.options.htmlContent=='' && this.options.actionName){
            this.options.htmlContent = this.options.actionName+'.html';
                    //+(window.hWin.HAPI4.getLocale()=='FRE'?'_fre':'')
        }
        
        this._super();
    },
    
    //  
    // invoked from _init after loading of html content
    //
    _initControls:function(){
        // init controls
        this._$('button').button();
        this._on(this._$('button.ui-button-action'),{click:this.doAction});
        
        this._$('#selDate').val((new Date()).toISOString().split('T')[0]);
        
        window.hWin.HEURIST4.ui.createRecordSelector( this._$('#selWebsite'), 
                    {'rst_PtrFilteredIDs':window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'],
                     'rst_PointerMode':'dropdown',
                     change: function(input){
                            //this.element.editing_input('getValues')[0]
                     }} );
        
        return this._super();
    },
    
    //
    //
    //
    doAction: function(){
        
        this._website_id = this._$('#selWebsite').editing_input('getValues');
        if(window.hWin.HEURIST4.util.isempty(this._website_id) 
            || !window.hWin.HEURIST4.util.isPositiveInt(this._website_id[0]))
        {
            window.hWin.HEURIST4.msg.showMsgFlash('Wensite is not defined', 1000);
            return;
        }
        this._website_id = this._website_id[0];
        
        const segment = `dimension1==${window.hWin.HAPI4.database};dimension2==web`;
        
        //show wait screen
        window.hWin.HEURIST4.msg.bringCoverallToFront(null, {opacity: '0.3'}, window.hWin.HR(this.options.title));
        this.element.css('cursor','progress'); //$('body')
        this._$('.ent_wrapper').hide();
        
        const period = this._$('#selPeriod').val();
        let date = this._$('#selDate').val();
        
        if(date.indexOf(',')<0){
            let d = new Date(date);
            //not range
            if(period=='day'){ //search for 7 days
               
               d.setDate(d.getDate() - 7);
                
            }else if(period=='week'){ //search for 8 weeks

               d.setDate(d.getDate() - 7*8);
                
            }else if(period=='month'){ //search for 12 months
                
               d.setMonth(d.getMonth() - 12); 
                
            }
            
            date = d.toISOString().split('T')[0]+','+date;
        }
        
        let request = {module: 'API', idSite:window.hWin.HAPI4.sysinfo.matomo_siteid, 
                        token_auth:window.hWin.HAPI4.sysinfo.matomo_api_key,
                        method: 'Actions.getPageUrls',  // 'VisitsSummary.get'
                        segment: segment, //this._$('#selSegment').val(),
                        flat: 1,
                        // segment: 'dimension1==osmak_9a;dimension2==web', // ;dimension4==584
                        // dimension1==osmak_9a;dimension2==web
                        // dimension1==parramatta_region_food_cultures;dimension2==web
                        expanded: 1, date:date, period:period, format:'json'};

        let that = this;
        window.hWin.HEURIST4.util.sendRequest('https://'+window.hWin.HAPI4.sysinfo.matomo_url+'/index.php',
                    request, null, function(response){that._afterActionEvenHandler(response)});
    },

    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler2: function( response ){

        this.element.css('cursor','auto');
        window.hWin.HEURIST4.msg.sendCoverallToBack(true);
        
        this._$('.ent_wrapper').hide();

        let div_res = this._$("#div_result").show();
        div_res.text(JSON.stringify(response));
        //div_res.html('<xmp>'+response.message+'</xmp>');
    },
    
    //  -----------------------------------------------------
    //
    //  after save event handler
    //
    _afterActionEvenHandler: function( response ){

        this.element.css('cursor','auto');
        window.hWin.HEURIST4.msg.sendCoverallToBack(true);
        
        this._$('.ent_wrapper').hide();

        let data = window.hWin.HEURIST4.util.isJSON(response)
        if(!data){
            //show error message
            window.hWin.HEURIST4.msg.showMsgErr(response);
            this._$('#div_header').show(); //show first page
            return;
        }

        let div_res = this._$("#div_result").show();
        
        const segment = `/${window.hWin.HAPI4.database}/web/${this._website_id}`;
        
        //data: [{x: '2016-12-25', y: 20}, {x: '2016-12-26', y: 10}]
        let nb_hits = [];
        let nb_visits = [];
        let datatable = [];
        
        let dates = Object.keys(data);
        
        for(let k=0; k<dates.length; k++){
            let tot_hits = 0;
            let tot_visits = 0;
            const range = dates[k];
            for(let m=0; m<data[range].length; m++){
                if (data[range][m]['label'].indexOf(segment)==0) { 
                    tot_hits = tot_hits + data[range][m]['nb_hits'];
                    tot_visits = tot_visits + data[range][m]['nb_visits'];
                    if(k==dates.length-1){
                        datatable.push(data[range][m]);            
                    }
                }
            }
            nb_hits.push({x:range, y:tot_hits});
            nb_visits.push({x:range, y:tot_visits});
        }
        
        /*remove redundant data
        let i = data.length;
        while (i--) {
            if (data[i]['label'].indexOf(segment)<0) { 
                data.splice(i, 1);
            } 
        }
        */
        
        div_res.empty();
        
        let classes = 'display compact nowrap cell-border';
                            
        this.div_datatable = $('<table>').css({'width':'98%'})
            .addClass(classes).appendTo(div_res);

        this.div_chart = $('<canvas width="800" height="250"></canvas>').appendTo(div_res);

        //div_res.text(JSON.stringify(data));
        this.div_datatable.DataTable({
            dom:'fti', //f - search, r- ,t-table, i-counter, p-pagination
            data: datatable,
            columns: [
                { data: 'label', title:'URL' },
                { data: 'nb_hits', title:'Pageviews'  },
                { data: 'nb_visits', title:'Unique Pageviews'  },
                { data: 'bounce_rate', title:'Bounce Rate'  },
                { data: 'avg_time_on_page', title:'Avg. time on page'  },
                { data: 'exit_rate', title:'Exit rate'  },
                { data: 'avg_page_load_time', title:'Avg. page load time'  }
            ]
        });   
        
        const chart_datasets = {
          //labels: labels,
          datasets: [{
            label: 'Pageviews',
            data: nb_hits,  
            fill: false,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
          },
          {
            label: 'Unique Pageviews',
            data: nb_visits,  
            fill: false,
            borderColor: 'rgb(192, 192, 75)',
            tension: 0.1
          }
          ]
        };        

        let visitChart = new Chart(this.div_chart, {type: 'line', data: chart_datasets});     

/*
<tr class="level0">
                  <th class="sortable first label" id="label">
                              <div id="thDIV" class="thDIV">Page URL</div>
           </th>
                  <th class="sortable columnSorted" id="nb_hits">
                                  <div class="columnDocumentation">
                       <div class="columnDocumentationTitle">
                           <span class="icon-help"></span>
                           Pageviews
                       </div>
                       The number of times this page was visited.
                   </div>
                              <div id="thDIV" class="thDIV"><span class="sortIcon desc " width="16" height="16"></span>Pageviews</div>
           </th>
                  <th class="sortable  " id="nb_visits">
                                  <div class="columnDocumentation" style="margin-left: -52.456px; margin-top: 80px; top: 0px; display: block;">
                       <div class="columnDocumentationTitle">
                           <span class="icon-help"></span>
                           Unique Pageviews
                       </div>
                       The number of visits that included this page. If a page was viewed multiple times during one visit, it is only counted once.
                   </div>
                              <div id="thDIV" class="thDIV">Unique Pageviews</div>
           </th>
                  <th class="sortable  " id="bounce_rate">
                                  <div class="columnDocumentation">
                       <div class="columnDocumentationTitle">
                           <span class="icon-help"></span>
                           Bounce Rate
                       </div>
                       The percentage of visits that started on this page and left the website straight away.
                   </div>
                              <div id="thDIV" class="thDIV">Bounce Rate</div>
           </th>
                  <th class="sortable  " id="avg_time_on_page">
                                  <div class="columnDocumentation">
                       <div class="columnDocumentationTitle">
                           <span class="icon-help"></span>
                           Avg. time on page
                       </div>
                       The average amount of time visitors spent on this page (only the page, not the entire website).
                   </div>
                              <div id="thDIV" class="thDIV">Avg. time on page</div>
           </th>
                  <th class="sortable  " id="exit_rate">
                                  <div class="columnDocumentation" style="margin-left: -69.0812px; margin-top: 80px; top: 0px; display: none;">
                       <div class="columnDocumentationTitle">
                           <span class="icon-help"></span>
                           Exit rate
                       </div>
                       The percentage of visits that left the website after viewing this page.
                   </div>
                              <div id="thDIV" class="thDIV">Exit rate</div>
           </th>
                  <th class="sortable last " id="avg_page_load_time">
                                  <div class="columnDocumentation">
                       <div class="columnDocumentationTitle">
                           <span class="icon-help"></span>
                           Avg. page load time
                       </div>
                       Average time (in seconds) it takes from requesting a page until the page is fully rendered within the browser
                   </div>
                              <div id="thDIV" class="thDIV">Avg. page load time</div>
           </th>
          </tr>        
*/        
    }
});