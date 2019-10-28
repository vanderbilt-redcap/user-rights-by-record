##User Rights by Record Documentation
###Required REDCap project
This external module requires a REDCap project be created to store custom user rights settings on a per user basis. A basic data dictionary for this can be downloaded <a onclick='downloadDD'>here</a>. For further explanation of the 
 <script type="javascript">
 function downloadDD() {
    var link = document.createElement("a");
    link.download = 'UserRightsModuleSettings_DataDictionary.csv';
    link.href = 'https://raw.githubusercontent.com/vanderbilt-redcap/user-rights-by-record/master/includes/UserRightsModuleSettings_DataDictionary.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    delete link;
 }
 </script>