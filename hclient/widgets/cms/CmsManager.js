/**
*  CmsManager - select CMS to view and edit, addition new website or page
*
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

/* global hLayoutMgr */

/**
 * Class: CmsManager
 * 
 * The CmsManager class is responsible for managing the CMS (Content Management System) 
 * functionalities such as selecting, viewing, and editing websites or pages.
 * It also provides methods for creating new websites or pages.
 */
class CmsManager {

    cms_home_counts = null;    

    RT_CMS_HOME;
    RT_CMS_MENU;

    DT_CMS_TOP_MENU;
    DT_CMS_MENU;
    DT_NAME;
    DT_CMS_HEADER;
    DT_LANGUAGES;
    DT_CMS_PAGETYPE;
    
    /**
     * Constructor: Initializes the CmsManager instance.
     * It does not take any parameters, but loads the CMS-specific constants later when needed.
     */
    constructor() {
    }

    /**
     * Private Method: #initDefCodes
     * 
     * Initializes CMS-specific codes from system constants. These include record types and field definitions related to CMS.
     * This method is called internally to load necessary definitions.
     * 
     * @private
     */
    #initDefCodes() {
        this.RT_CMS_HOME = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_HOME'];
        this.RT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['RT_CMS_MENU'];

        this.DT_CMS_TOP_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_TOP_MENU'];
        this.DT_CMS_MENU = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_MENU'];
        this.DT_NAME = window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'];
        this.DT_CMS_HEADER = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_HEADER'];
        this.DT_LANGUAGES = window.hWin.HAPI4.sysinfo['dbconst']['DT_LANGUAGES'];
        this.DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];
    }

    /**
     * Method: executeAction
     * 
     * Executes a CMS action based on the given action ID. Depending on the action type, it may create a website, create a page, or edit/view an existing page or website.
     * 
     * @param {string} actionid - The ID of the action to execute.
     */
    executeAction(actionid) {
        if (!this.isCmsAllowedOnThisServer()) {
            return;
        }

        if (!this.checkRequiredRecordTypes(() => {
            this.executeAction(actionid);
        })) {
            return;
        }

        this.cms_home_counts = null; // Reset

        switch (actionid) {
            case "menu-cms-create":
                this.#createWebSite();
                break;
            case "menu-cms-create-page":
                this.#createPage();
                break;
            case 'menu-cms-edit-page':
            case 'menu-cms-view-page':
                this.#selectPage(actionid, -1);
                break;
            case 'menu-cms-edit':
            case 'menu-cms-view':
                this.#selectWebSite(actionid);
                break;
        }
    }

    /**
     * Method: isCmsAllowedOnThisServer
     * 
     * Checks if CMS creation is allowed on the current server based on security settings.
     * 
     * @returns {boolean} Returns `true` if CMS is allowed, otherwise `false`.
     */
    isCmsAllowedOnThisServer() {
        if (window.hWin.HAPI4.sysinfo['cms_allowed'] == -1) {
            window.hWin.HEURIST4.msg.showMsgErr('Due to security restrictions, website creation is blocked on this server.' +
                '<br>Please contact system administrator (' + window.hWin.HAPI4.sysinfo.sysadmin_email + ') ' +
                'if you wish to create a website.');
            return false;
        }
        return true;
    }

    /**
     * Method: checkRequiredRecordTypes
     * 
     * Ensures that the required record types and fields for CMS are available. If not, it triggers a system check to import missing types.
     * 
     * @param {function} callback - A callback function that is executed after the check is completed.
     * @returns {boolean} Returns `true` if all required record types are present, otherwise `false`.
     */
    checkRequiredRecordTypes(callback) {
        this.#initDefCodes();
        
        let missing = '';

        if (!(this.RT_CMS_HOME > 0 && this.RT_CMS_MENU > 0)) {
            missing = 'You will need record types 99-51 (Web home) and 99-52 (Web menu/content)';
        } else if (!(this.DT_LANGUAGES > 0) || !$Db.rst(this.RT_CMS_HOME, this.DT_LANGUAGES)) {
            missing = 'You will need record types 99-51 (Web home) with field 2-967 (Languages)';
        }

        if (missing != '') {
            window.hWin.HAPI4.SystemMgr.checkPresenceOfRectype('99-51', 2,
                missing + ' which are available as part of Heurist_Core_Definitions.',
                callback,
                true  // Force import
            );
            return false;
        }

        return true;
    }

    /**
     * Private Method: #selectPage
     * 
     * Opens a dialog to select or create a CMS page.
     * 
     * @private
     * @param {string} action - The action ID.
     * @param {number} count - Number of existing CMS page records.
     */
    #selectPage(action, count) {
        let that = this;

        if (count < 0) {
            this.#getCountWebPageRecords((count) => {
                that.#selectPage(action, count);
            });
            return;
        }

        if (count == 0) {
            this.#createPage();
            return;
        }

        let is_view_mode = (action == 'menu-cms-view-page');

        let query_search_pages = { t: this.RT_CMS_MENU, sort: '-id' };
        query_search_pages['f:' + this.DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];

        this.#openCMSlist(window.hWin.HR('Select Web page'), query_search_pages, is_view_mode);
    }

    /**
     * Private Method: #createPage
     * 
     * Opens a dialog to create a new CMS page.
     * 
     * @private
     */
    #createPage() {
        let that = this;
        let $dlg = window.hWin.HEURIST4.msg.showPrompt(
            window.hWin.HR('Name for new page') + ':',
            function(value) {
                if (window.hWin.HEURIST4.util.isempty(value)) {
                    window.hWin.HEURIST4.msg.showMsgFlash('Specify name', 1000);
                } else {
                    let popup_options = { record_id: -2, webpage_title: value };
                    that.#openCMSedit(popup_options);
                }
            },
            { title: window.hWin.HR('New standalone web page'), yes: window.hWin.HR('Create'), no: window.hWin.HR('Cancel') },
            { default_palette_class: 'ui-heurist-publish' }
        );
    }

    /**
     * Private Method: #selectWebSite
     * 
     * Opens a dialog to select or create a CMS website.
     * 
     * @private
     * @param {string} action - The action ID (either view or edit).
     */
    #selectWebSite(action) {
        let that = this;

        if (this.cms_home_counts == null) {
            this.#getCountWebSiteRecords(() => {
                that.#selectWebSite(action);
            });
            return;
        }

        if (this.cms_home_counts.count == 0) {
            this.#createWebSite();
            return;
        }

        let is_view_mode = (action == 'menu-cms-view');

        if (this.cms_home_counts.count == 1) {
            this.#openCMS(0, is_view_mode ? '' : 'edit');
            return;
        }

        let query_search_sites = { t: this.RT_CMS_HOME, sort: '-id' };

        this.#openCMSlist(window.hWin.HR('Select Website'), query_search_sites, is_view_mode);

        if (this.cms_home_counts.sMsgCmsPrivate != '') {
            window.hWin.HEURIST4.msg.showMsgDlg(this.cms_home_counts.sMsgCmsPrivate, null,
                'Non-public website records',
                { default_palette_class: 'ui-heurist-publish' });
        }
    }

    /**
     * Private Method: #openCMSlist
     * 
     * Displays a list of CMS items (pages or websites) for the user to select.
     * 
     * @private
     * @param {string} sTitle - The title of the selection dialog.
     * @param {Object} query_search - Search parameters for filtering the CMS items.
     * @param {boolean} is_view_mode - If true, items will be opened in view mode, otherwise edit mode.
     */
    #openCMSlist(sTitle, query_search, is_view_mode) {
        let that = this;

        let popup_options = {
            select_mode: 'select_single',
            select_return_mode: 'recordset',
            edit_mode: 'popup',
            selectOnSave: true,
            title: sTitle,
            fixed_search: query_search,
            layout_mode: 'listonly',
            width: 500, height: 400,
            default_palette_class: 'ui-heurist-publish',
            resultList: {
                show_toolbar: false,
                view_mode: 'icons',
                renderer: function(recordset, record) {
                    let recID = recordset.fld(record, 'rec_ID');
                    let recTitle = recordset.fld(record, 'rec_Title');
                    let recTitle_strip_all = window.hWin.HEURIST4.util.htmlEscape(recTitle);
                    let html = '<div class="recordDiv" id="rd' + recID + '" recid="' + recID + '">' + recTitle_strip_all + '</div>';
                    return html;
                }
            },
            onselect: function(event, data) {
                if (window.hWin.HEURIST4.util.isRecordSet(data.selection)) {
                    let recordset = data.selection;
                    let rec_ID = recordset.getOrder()[0];
                    that.#openCMS(rec_ID, is_view_mode ? '' : 'edit');
                }
            }
        };

        window.hWin.HEURIST4.ui.showEntityDialog('records', popup_options);
    }

    /**
     * Private Method: #createWebSite
     * 
     * Opens a dialog to create a new CMS website.
     * 
     * @private
     */
    #createWebSite() {
        let that = this;

        if (this.cms_home_counts == null) {
            this.#getCountWebSiteRecords(() => {
                that.#createWebSite();
            });
            return;
        }

        let sMsg = '';

        if (this.cms_home_counts.count > 0) {
            sMsg = 'You already have ' +
                ((this.cms_home_counts.count == 1) ? 'a website' :
                    (this.cms_home_counts.count + ' websites')) +
                '. Are you sure you want to create an additional site?';

            if (this.cms_home_counts.sMsgCmsPrivate != '') {
                sMsg = sMsg + this.cms_home_counts.sMsgCmsPrivate;
            }
        } else {
            sMsg = 'Are you sure you want to create a site?';
        }

        sMsg = sMsg + '<p>Check the box if you wish to keep your website private ' +
            '<br><input type="checkbox"> hide website (can be changed later)</p>';

        let $dlg = window.hWin.HEURIST4.msg.showMsgDlg(sMsg,
            function() {
                let chb = $dlg.find('input[type="checkbox"]');
                let is_private = chb.is(':checked');

                let popup_options = { record_id: -1, webpage_private: is_private };

                that.#openCMSedit(popup_options);
            },
            window.hWin.HR('New website'),
            { default_palette_class: 'ui-heurist-publish' }
        );
    }

    /**
     * Private Method: #openCMS
     * 
     * Opens a CMS page or website in either view or edit mode.
     * 
     * @private
     * @param {number} rec_ID - The record ID of the CMS item.
     * @param {string} mode - The mode to open the CMS in ('edit', 'development', or 'production').
     */
    #openCMS(rec_ID, mode) {
        if (mode == 'edit') {
            this.#openCMSedit({ record_id: rec_ID });
            return;
        }

        let url = window.hWin.HAPI4.baseURL;

        if (mode == 'production') {
            url = window.hWin.HAPI4.baseURL_pro;
        } else if (mode != 'development' && window.hWin.HAPI4.baseURL != window.hWin.HAPI4.baseURL_pro) {
            let that = this;
            let buttons = {};
            buttons[window.hWin.HR('Current (development) version')] = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                $dlg.dialog("close");
                that.#openCMS(rec_ID, 'development');
            };
            buttons[window.hWin.HR('Production version')] = function() {
                let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                $dlg.dialog("close");
                that.#openCMS(rec_ID, 'production');
            };

            window.hWin.HEURIST4.msg.showMsgDlg('<p>You are currently running a development version of Heurist.</p>' +
                '<p>Reply "Current (development) version" to use the development version for previewing your site, but please do not publish this URL.</p>' +
                '<p>Reply "Production version" to obtain the URL for public dissemination, which will load the production version of Heurist.</p>',
                buttons, null, { default_palette_class: 'ui-heurist-publish' });

            return;
        }

        url = url + '?db=' + window.hWin.HAPI4.database + '&website';
        if (rec_ID > 0) {
            url = url + '&id=' + rec_ID;
        }

        window.open(url, '_blank');
    }

    /**
     * Private Method: #getCountWebPageRecords
     * 
     * Retrieves the count of CMS page records with the type "page".
     * 
     * @private
     * @param {function} callback - A callback function that is executed after the count is retrieved.
     */
    #getCountWebPageRecords(callback) {
        let DT_CMS_PAGETYPE = window.hWin.HAPI4.sysinfo['dbconst']['DT_CMS_PAGETYPE'];

        let query_search_pages = { t: this.RT_CMS_MENU };
        query_search_pages['f:' + DT_CMS_PAGETYPE] = window.hWin.HAPI4.sysinfo['dbconst']['TRM_PAGETYPE_WEBPAGE'];

        let request = { q: query_search_pages, w: 'a', detail: 'count', source: 'getCountWebPageRecords' };
        window.hWin.HAPI4.RecordMgr.search(request, function(response) {
            if (response.status == window.hWin.ResponseStatus.OK) {
                callback.call(this, response.data.count);
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }

    /**
     * Private Method: #getCountWebSiteRecords
     * 
     * Retrieves the count of CMS website (RT_CMS_HOME) records and the number of private records among them.
     * 
     * @private
     * @param {function} callback - A callback function that is executed after the count is retrieved.
     */
    #getCountWebSiteRecords(callback) {
        let request = {
            'a': 'counts',
            'entity': 'defRecTypes',
            'mode': 'cms_record_count',
            'ugr_ID': window.hWin.HAPI4.currentUser['ugr_ID']
        };

        let that = this;

        window.hWin.HAPI4.EntityMgr.doRequest(request, function(response) {
            if (response.status == window.hWin.ResponseStatus.OK) {
                that.cms_home_counts = { count: response.data['all'], sMsgCmsPrivate: '' };

                let aPriv = response.data['private'];

                if (aPriv.length > 0) {
                    let cnt_home = response.data['private_home'];
                    let cnt_menu = response.data['private_menu'];
                    let s1 = '';

                    if (cnt_home > 0) {
                        if (cnt_home == 1) {
                            s1 = '<p>Note: CMS website record is non-public. This website is not visible to the public.</p>';
                        } else {
                            s1 = '<p>Note: There are ' + cnt_home + ' non-public CMS website records. These websites are not visible to the public.</p>';
                        }
                    }

                    that.cms_home_counts.sMsgCmsPrivate =
                        '<div style="margin-top:10px;padding:4px">' +
                        s1 +
                        ((cnt_menu > 0) ? ('<p>Warning: There are ' + cnt_menu + ' non-public CMS menu/page records. Database login is required to see these pages in the website.') : '') +
                        '<br><br>' +
                        '<a target="_blank" href="' + window.hWin.HAPI4.baseURL +
                        '?db=' + window.hWin.HAPI4.database + '&q=ids:' + aPriv.join(',') + '">Click here</a>' +
                        ' to view these records and set their visibility ' +
                        'to Public (use Share > Ownership/Visibility)';
                }

                if (window.hWin.HEURIST4.util.isFunction(callback)) callback(that);
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        });
    }

    /**
     * Private Method: #openCMSedit
     * 
     * Opens a CMS page or website in edit mode.
     * 
     * @private
     * @param {Object} options - Configuration options for the CMS item being edited.
     */
    #openCMSedit(options) {
        if (options.record_id < 0) {
            this.#createNewWebContent(options);
            return;
        }

        let sURL = window.hWin.HAPI4.baseURL + '?db=' + window.hWin.HAPI4.database + '&website&id=' + options.record_id + '&edit=2';

        if (options.newlycreated) {
            sURL = sURL + '&newlycreated';
        }
        window.open(sURL, '_blank');
    }

    /**
     * Private Method: #createNewWebContent
     * 
     * Creates new web content (webpage or website) by importing default records.
     * 
     * @private
     * @param {Object} options - Configuration options such as page title and privacy settings.
     */
    #createNewWebContent(options) {
        let home_page_record_id = options.record_id,
            webpage_title = options.webpage_title,
            webpage_private = (options.webpage_private == true);

        let isWebPage = (home_page_record_id == -2);

        window.hWin.HEURIST4.msg.bringCoverallToFront();
        window.hWin.HEURIST4.msg.showMsgFlash(
            (isWebPage ? 'Creating default layout (webpage) record' : 'Creating the set of website records'),
            10000
        );

        let request = {
            action: 'import_records',
            filename: isWebPage ? 'webpageStarterRecords.xml' : 'websiteStarterRecords.xml',
            is_cms_init: 1,
            make_public: (webpage_private === true) ? 0 : 1,
            id: window.hWin.HEURIST4.util.random()
        };

        let that = this;

        function __callback(response) {
            let $dlg = window.hWin.HEURIST4.msg.getMsgFlashDlg();
            $dlg.dialog('close');

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if (response.status == window.hWin.ResponseStatus.OK) {
                $('#spanRecCount2').text(response.data.count_imported);

                if (isWebPage) {
                    if (!window.hWin.HEURIST4.util.isempty(webpage_title)) {
                        let page_recid = response.data.ids[0];

                        let request = {
                            a: 'replace',
                            recIDs: page_recid,
                            dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_NAME'],
                            rVal: webpage_title
                        };
                        window.hWin.HAPI4.RecordMgr.batch_details(request, function(response) {
                            if (response.status == window.hWin.ResponseStatus.OK) {
                                options.record_id = page_recid;
                                options.newlycreated = true;
                                that.#openCMSedit(options);
                            } else {
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                        });
                        return;
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL + 'hclient/widgets/cms/editCMS_NewSiteMsg.html');

                    if (response.data.page_id_for_blog > 0) {
                        that.#addTemplate('blog', response.data.page_id_for_blog);
                    }

                    options.record_id = response.data.home_page_id > 0 ? response.data.home_page_id : response.data.ids[0];
                    that.#openCMSedit(options);
                }
            } else {
                window.hWin.HEURIST4.msg.showMsgErr(response);
            }
        }

        window.hWin.HAPI4.doImportAction(request, __callback);
    }

    /**
     * Private Method: #addTemplate
     * 
     * Replaces the content of a CMS page with a predefined template.
     * 
     * @private
     * @param {string} template_name - The name of the template to apply.
     * @param {number} affected_page_id - The ID of the page to update with the template content.
     */
    #addTemplate(template_name, affected_page_id) {
        let sURL = window.hWin.HAPI4.baseURL + 'hclient/widgets/cms/templates/snippets/' + template_name + '.json';

        $.getJSON(sURL, function(new_element_json) {
            if (!window.layoutMgr) {
                hLayoutMgr();
            }

            window.layoutMgr.prepareTemplate(new_element_json, function(updated_json) {
                let request = {
                    a: 'replace',
                    recIDs: affected_page_id,
                    dtyID: window.hWin.HAPI4.sysinfo['dbconst']['DT_EXTENDED_DESCRIPTION'],
                    rVal: JSON.stringify(updated_json)
                };
                window.hWin.HAPI4.RecordMgr.batch_details(request, function(response) {
                    if (response.status != window.hWin.ResponseStatus.OK) {
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
            });
        });
    }
}
