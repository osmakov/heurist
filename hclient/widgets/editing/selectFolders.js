/**
* Widget to select folders from  tree
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

$.widget( "heurist.selectFolders", $.heurist.selectMultiValues, {

    // default options
    options: {
        title: 'Select Folders',
        
        emptyMessage: 'No folders found',
        
        allowEdit: true,
    },
    
    _show_system_folders:false,

    // the constructor
    _init: function() {

        this._super();
        const that = this;

        let ent_header = this.element.find('.ent_header');        

        $('<div>').button({label:window.hWin.HR('New folder')}).on('click',
            function() {
                let node = that._treeview.fancytree('getRootNode');
                node.editCreateNode("child", "new folder");                    
            }        
        ).appendTo(ent_header);

        $('<div>').button({label:window.hWin.HR('New subfolder')}).on('click',
            function() {

                let node = that._treeview.fancytree("getActiveNode");
                if( !node ) {
                    window.hWin.HEURIST4.msg.showMsgFlash('Select parent folder');
                    return;
                }
                if(node.data.issystem){
                    window.hWin.HEURIST4.msg.showMsgFlash('System folder cannot be modified');
                    return;
                }
                node.editCreateNode("child", "new folder");                    
            }        
        ).appendTo(ent_header);

        $('<div>').button({label:window.hWin.HR('Delete')}).on('click',
            function() {

                let node = that._treeview.fancytree("getActiveNode");
                if(node){
                    if(node.data.issystem){
                        window.hWin.HEURIST4.msg.showMsgFlash('System folder cannot be deleted');
                    }else if(node.countChildren()>0 || node.data.files_count>0){
                        window.hWin.HEURIST4.msg.showMsgFlash('Cannot delete non-empty folder');
                    }else{

                        let path = node.getParent().getKeyPath();
                        path = (path=='/')?'':(path+'/');
                        let currname = path+node.title;

                        window.hWin.HAPI4.SystemMgr.get_sysfolders({operation:'delete', name:currname}, 
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){
                                    node.remove();
                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response);
                                }
                        });
                    }
                }
            }
        ).appendTo(ent_header);

        /*
        $('<label><input type="checkbox">Show system folders</label>').css({'margin-left':'20px'}).appendTo(ent_header);
        ent_header.find('input').on('click',
        function(event){
        that._show_system_folders = $(event.target).is(':checked');

        var wtrr = $.ui.fancytree.getTree(that._treeview);
        wtrr.filterBranches(function(node){
        return that._show_system_folders || !node.data.issystem;
        }, {mode: "hide"});
        //that._treeview.fancytree('render')  
        });
        */

        if(this.options.allowEdit){
            ent_header.show();
        }

    }, //end _create
    
    //
    //
    //
    _initList: function(){
        
        if(Array.isArray(this.options.allValues) && this.options.allValues.length>0){
            
            this._showAsDialog();
            this._initTreeView( this.options.allValues );    
            
        }else{

            //search for folders
            let that = this;                            
            let opts = {};
            if(this.options.root_dir){
                opts.root_dir = this.options.root_dir;
            }
       
            window.hWin.HAPI4.SystemMgr.get_sysfolders(opts, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        that.options.allValues = response.data;
                        if(Array.isArray(that.options.allValues) && that.options.allValues.length>0){
                            that._initList();
                        }else{
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR(that.options.emptyMessage));                
                        }
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
                });
        }

    }
    
});
