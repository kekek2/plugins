{#

OPNsense® is Copyright © 2014 – 2017 by Deciso B.V.
This file is Copyright © 2017 by Michael Muenz
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}
<div class="alert alert-warning" role="alert" id="missing_clamav" style="display:none;min-height:65px;">
    <div style="margin-top: 8px;">{{ lang._('No ClamAV plugin found, please install via System > Firmware > Plugins.')}}</div>
</div>
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#antivirus">{{ lang._('Antivirus') }}</a></li>
    <li><a data-toggle="tab" href="#logview">{{ lang._('Log view') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="general" class="tab-pane fade in active">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
            <hr />
            <div class="col-md-12">
                <button class="btn btn-primary"  id="saveAct" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct_progress" class=""></i></button>
            </div>
        </div>
    </div>
    <div id="antivirus" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':antivirusForm,'id':'frm_antivirus_settings'])}}
            <hr />
            <div class="col-md-12">
                <button class="btn btn-primary"  id="saveAct2" type="button"><b>{{ lang._('Save') }}</b><i id="saveAct2_progress" class=""></i></button>
            </div>
        </div>
    </div>
    <div id="logview" class="tab-pane fade in">
        <p>
        <div class="input-group">
            <div class="input-group-addon"><i class="fa fa-search"></i></div>
            <input type="text" class="form-control" id="filtertext" name="filtertext" placeholder="{{lang._('Search for a specific message...')}}"/>
            <input type="submit" id="downloadLogFileAction" class="btn btn-primary pull-right" value="{{lang._('Download log file')}}" />
        </div>
        </p>
        <div id="logview" class="content-box-main">
            <table id="grid-logview" class="table table-striped table-hover">
                <thead>
                <tr>
                    <th class="col-xs-2">{{lang._('Time')}}</th>
                    <th>{{lang._('Message')}}</th>
                </tr>
                </thead>
            </table>
        </div>
        <p>
        <div class="input-group">
            <input id="clearAction" type="submit" class="btn btn-primary" value="{{lang._('Clear log')}}"/>
        </div>
        </p>
    </div>
</div>

<script type="text/javascript">
    $( document ).ready(function() {
        function fillRows(data) {
            while($("#grid-logview")[0].rows.length > 1)
            {
                $("#grid-logview")[0].deleteRow(1);
            }

            if(!data || !data.data || !data.data.length)
                return;

            for(i = 0; i < data.data.length; i++)
            {
                var row = $("#grid-logview")[0].insertRow();
                var td = row.insertCell(0);
                td.innerHTML = data.data[i].time;
                td.class = "listlr";
                td = row.insertCell(1);
                td.innerHTML = data.data[i].message;
                td.class = "listr";
            }
        };

        var data_get_map = {'frm_general_settings':"/api/cicap/general/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        var data_get_map2 = {'frm_antivirus_settings':"/api/cicap/antivirus/get"};
        mapDataToFormUI(data_get_map2).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        ajaxCall(url="/api/cicap/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
        });

	    // check if ClamAV plugin is installed
        ajaxCall(url="/api/cicap/service/checkclamav", sendData={}, callback=function(data,status) {
	    if (data == "0") {
                $('#missing_clamav').show();
            }
        });

        ajaxCall(url="/api/cicap/service/getlog", sendData={},callback=function(data,status) {
            fillRows(data);
        });

        $("#filtertext").keyup(function(e){
            if(e.keyCode == 13) {
                ajaxCall(url="/api/cicap/service/getlog", sendData={'filter': $("#filtertext").val() },callback=function(data,status) {
                    fillRows(data);
                });
            }
        });

        $("#clearAction").click(function(){
            ajaxCall(url="/api/cicap/service/clearLog", sendData={},callback=function(data,status) {

                ajaxCall(url="/api/cicap/service/getlog", sendData={},callback=function(data,status) {
                    fillRows(data);
                });
            });
        });

        $("#downloadLogFileAction").click(function(){
            window.location = "/api/cicap/service/download";
        });

        // link save button to API set action
        $("#saveAct").click(function(){
            saveFormToEndpoint(url="/api/cicap/general/set", formid='frm_general_settings',callback_ok=function(){
		    $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/cicap/service/reconfigure", sendData={}, callback=function(data,status) {
                            ajaxCall(url="/api/cicap/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                            });
			    $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });
        $("#saveAct2").click(function(){
            saveFormToEndpoint(url="/api/cicap/antivirus/set", formid='frm_antivirus_settings',callback_ok=function(){
		    $("#saveAct2_progress").addClass("fa fa-spinner fa-pulse");
                    ajaxCall(url="/api/cicap/service/reconfigure", sendData={}, callback=function(data,status) {
                            ajaxCall(url="/api/cicap/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                            });
			    $("#saveAct2_progress").removeClass("fa fa-spinner fa-pulse");
                    });
            });
        });
        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
    });
</script>
