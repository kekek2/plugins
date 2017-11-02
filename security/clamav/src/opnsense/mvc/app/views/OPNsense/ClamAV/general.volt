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

<div class="alert alert-warning" role="alert" id="dl_sig_alert" style="display:none;min-height:65px;">
    <button class="btn btn-primary pull-right" id="dl_sig" type="button">{{ lang._('Download signatures') }} <i id="dl_sig_progress"></i></button>
    <div style="margin-top: 8px;">{{ lang._('No signature database found, please download before use. The download will take several minutes and this message will disappear when it has been completed.') }}</div>
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#versions">{{ lang._('Versions') }}</a></li>
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
    <div id="versions" class="tab-pane fade in">
        <div class="content-box" style="padding-bottom: 1.5em;">
            {{ partial("layout_partials/base_form",['fields':versionForm,'id':'frm_version'])}}
        </div>
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
            <button class="btn btn-primary"  id="clearAction" type="button"><b>{{ lang._('Clear log') }}</b> <i id="applyClear_progress" class=""></i></button>
        </div>
        </p>
    </div>
</div>

<script type="text/javascript">
function timeoutCheck() {
    ajaxCall(url="/api/clamav/service/freshclam", sendData={}, callback=function(data,status) {
        if (data['status'] == 'done') {
            $("#dl_sig_progress").removeClass("fa fa-spinner fa-pulse");
            $("#dl_sig").prop("disabled", false);
            $('#dl_sig_alert').hide();
        } else {
            setTimeout(timeoutCheck, 2500);
        }
    });
}

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

    var data_get_map = {'frm_general_settings':"/api/clamav/general/get"};
    mapDataToFormUI(data_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    var version_get_map = {'frm_version':"/api/clamav/service/version"};
    mapDataToFormUI(version_get_map).done(function(data){
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
    });

    ajaxCall(url="/api/clamav/service/status", sendData={}, callback=function(data,status) {
        updateServiceStatusUI(data['status']);
    });

    ajaxCall(url="/api/clamav/service/freshclam", sendData={}, callback=function(data,status) {
        if (data['status'] != 'done') {
            if (data['status'] == 'running') {
                $("#dl_sig_progress").addClass("fa fa-spinner fa-pulse");
                $("#dl_sig").prop("disabled", true);
                setTimeout(timeoutCheck, 2500);
            }
            $('#dl_sig_alert').show();
        }
    });

    ajaxCall(url="/api/clamav/service/getlog", sendData={},callback=function(data,status) {
        fillRows(data);
    });

    $("#filtertext").keyup(function(e){
        if(e.keyCode == 13) {
            ajaxCall(url="/api/clamav/service/getlog", sendData={'filter': $("#filtertext").val() },callback=function(data,status) {
                fillRows(data);
            });
        }
    });

    $("#clearAction").click(function(){
        $("#applyClear_progress").addClass("fa fa-spinner fa-pulse");
        $("#clearAction").addClass("disabled");
        $("#downloadLogFileAction").addClass("disabled");
        ajaxCall(url="/api/clamav/service/clearLog", sendData={},callback=function(data,status) {
            $("#applyClear_progress").removeClass("fa fa-spinner fa-pulse");
            $("#clearAction").removeClass("disabled");
            $("#downloadLogFileAction").removeClass("disabled");
            ajaxCall(url="/api/clamav/service/getlog", sendData={},callback=function(data,status) {
                fillRows(data);
            });
        });
    });

    $("#downloadLogFileAction").click(function(){
        window.location = "/api/clamav/service/download";
    });

    $("#saveAct").click(function(){
        saveFormToEndpoint(url="/api/clamav/general/set", formid='frm_general_settings',callback_ok=function(){
        $("#saveAct_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall(url="/api/clamav/service/reconfigure", sendData={}, callback=function(data,status) {
                ajaxCall(url="/api/clamav/service/status", sendData={}, callback=function(data,status) {
                    updateServiceStatusUI(data['status']);
                });
                $("#saveAct_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });
    });

    $("#dl_sig").click(function(){
        $("#dl_sig_progress").addClass("fa fa-spinner fa-pulse");
        $("#dl_sig").prop("disabled", true);
        ajaxCall(url="/api/clamav/service/freshclam", sendData={action:1}, callback_ok=function(){
            setTimeout(timeoutCheck, 2500);
        });
    });

    // versions table view tuning
    $("#frm_version colgroup col").eq(2).removeClass("col-md-5");
    $("#frm_version colgroup col").eq(1).removeClass("col-md-4");

    // update history on tab state and implement navigation
    if(window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });
});
</script>
