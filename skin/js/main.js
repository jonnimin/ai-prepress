const printProducts={"business-card":{w:9,h:5.4,bleed:.2,img:"skin/img/print/business-card.png",defaultOrientation:"landscape"},"a4dm":{w:21,h:29.7,bleed:.2,img:"skin/img/print/a4-dm.png",defaultOrientation:"portrait"},"a3dm":{w:29.7,h:42,bleed:.2,img:"skin/img/print/large-format.png",defaultOrientation:"portrait"},"sticker-card":{w:9,h:5.4,bleed:.3,img:"skin/img/print/sticker.png",defaultOrientation:"landscape"},"postcard":{w:10.5,h:14.8,bleed:.2,img:"skin/img/print/postcard.png",defaultOrientation:"portrait"},"custom":{w:9,h:9,bleed:.2,img:"skin/img/print/package.png",defaultOrientation:"landscape"}};
let baseSize={w:9,h:5.4},currentFiles={front:null,back:null},currentAction="print_ready",currentOrientation="landscape",previewStates={front:{zoom:1,x:0,y:0},back:{zoom:1,x:0,y:0}},dragState={active:false,side:"front",startX:0,startY:0,startPanX:0,startPanY:0};
const usageKey="ai_prepress_pdf_generated_count_v24",accountKey="ai_prepress_account_bound_v24";
$(function(){bindMobileMenu();bindStep1();bindUpload();bindPreviewControls();bindCheckActions();bindRepairActions();bindModal();bindAccountModalEvents();bindPaymentModalEvents();bindCoopModalEvents();setProduct("business-card");enforceOptionGuards();updateCurrentToolName();});
function bindMobileMenu(){$(".mobile-toggle").on("click",()=>$(".site-nav").toggleClass("open"));}
function bindStep1(){$(".scene-card").on("click",function(){setProduct($(this).data("product"));});$("#productType").on("change",function(){setProduct($(this).val());});$("#printSide").on("change",enforceOptionGuards);$("#cornerRadius").on("change",function(){updatePreviewMeta();enforceOptionGuards();});$(".orientation-btn").on("click",function(){$(".orientation-btn").removeClass("active");$(this).addClass("active");currentOrientation=$(this).data("orientation");applyOrientation();});$("#widthCm,#heightCm,#bleedCm,#targetDpi").on("input change",function(){if($("#productType").val()==="custom"){baseSize={w:toNum($("#widthCm").val(),9),h:toNum($("#heightCm").val(),9)};}updatePreviewMeta();});}
function setProduct(key){const item=printProducts[key];if(!item)return;$(".scene-card").removeClass("active");$(`.scene-card[data-product="${key}"]`).addClass("active");$("#productType").val(key);baseSize={w:item.w,h:item.h};$("#bleedCm").val(String(item.bleed));currentOrientation=item.defaultOrientation==="portrait"?"portrait":"landscape";$(".orientation-btn").removeClass("active");$(`.orientation-btn[data-orientation="${currentOrientation}"]`).addClass("active");applyOrientation();if(!currentFiles.front)loadPreviewSource("front",item.img,true);if(!currentFiles.back)loadPreviewSource("back",item.img,true);enforceOptionGuards();}
function applyOrientation(){let w=baseSize.w,h=baseSize.h;if(currentOrientation==="portrait"&&w>h)[w,h]=[h,w];if(currentOrientation==="landscape"&&h>w)[w,h]=[h,w];$("#widthCm").val(round1(w));$("#heightCm").val(round1(h));updatePreviewMeta();}
function enforceOptionGuards(){const product=$("#productType").val(),allowDouble=product==="business-card"||product==="postcard",allowCorner=!(product==="a4dm"||product==="a3dm"),isDouble=$("#printSide").val()==="double";if($("#printSide").length){if(!allowDouble){$("#printSide").val("single").prop("disabled",true).attr("title","此品項目前僅開放單面設定");$(".back-side-panel").hide();}else{$("#printSide").prop("disabled",false).removeAttr("title");$(".back-side-panel").toggle(isDouble);}}if($("#cornerRadius").length){if(!allowCorner){$("#cornerRadius").val("0").prop("disabled",true).attr("title","A4DM / A3DM 不提供四邊圓角設定");}else{$("#cornerRadius").prop("disabled",false).removeAttr("title");}}updatePreviewMeta();}
function bindUpload(){$(".dropzone-side").on("click keydown",function(e){if(e.type==="click"||e.key==="Enter"||e.key===" "){$(this).find(".side-file-input").trigger("click");}});$(".side-file-input").on("click",e=>e.stopPropagation());$(".side-file-input").on("change",function(){const side=$(this).data("side");if(this.files&&this.files[0])handleFile(side,this.files[0]);});$(".dropzone-side").on("dragenter dragover",function(e){e.preventDefault();e.stopPropagation();$(this).addClass("is-drag");});$(".dropzone-side").on("dragleave",function(e){e.preventDefault();e.stopPropagation();$(this).removeClass("is-drag");});$(".dropzone-side").on("drop",function(e){e.preventDefault();e.stopPropagation();$(this).removeClass("is-drag");const side=$(this).data("side"),files=e.originalEvent.dataTransfer.files;if(files&&files[0])handleFile(side,files[0]);});}
function handleFile(side,file){currentFiles[side]=file;$(`#${side}FileName`).text(file.name);const ext=file.name.split(".").pop().toLowerCase();if(["jpg","jpeg","png","webp","gif","svg"].includes(ext)){loadPreviewSource(side,URL.createObjectURL(file),true);}else{loadPreviewSource(side,printProducts[$("#productType").val()].img,true);}$("#preflightReport").removeClass("show");$("#downloadLink").addClass("d-none").attr("href","#");}
function loadPreviewSource(side,src,reset){const $img=$(`.preview-img[data-side="${side}"]`);$img.off("load.preview").on("load.preview",()=>applyPreviewTransform(side)).attr("src",src);if(reset){previewStates[side]={zoom:1,x:0,y:0};$(`.zoomRange[data-side="${side}"]`).val(100);$(`.zoomLabel[data-side="${side}"]`).text("100%");}applyPreviewTransform(side);}
function bindPreviewControls(){$(".zoomRange").on("input change",function(){const side=$(this).data("side");previewStates[side].zoom=toNum(this.value,100)/100;$(`.zoomLabel[data-side="${side}"]`).text(this.value+"%");applyPreviewTransform(side);});$(".preview-img").each(function(){this.addEventListener("mousedown",startDrag);this.addEventListener("touchstart",startDrag,{passive:false});});$(".preview-stage").each(function(){this.addEventListener("wheel",function(e){e.preventDefault();const side=$(this).find(".preview-img").data("side");previewStates[side].zoom=Math.max(1,Math.min(2.5,previewStates[side].zoom+(e.deltaY<0?.08:-.08)));const pct=Math.round(previewStates[side].zoom*100);$(`.zoomRange[data-side="${side}"]`).val(pct);$(`.zoomLabel[data-side="${side}"]`).text(pct+"%");applyPreviewTransform(side);},{passive:false});});document.addEventListener("mousemove",onDrag);document.addEventListener("mouseup",endDrag);document.addEventListener("touchmove",onDrag,{passive:false});document.addEventListener("touchend",endDrag);}
function startDrag(e){e.preventDefault();const side=$(e.currentTarget).data("side");const p=getPoint(e);dragState={active:true,side:side,startX:p.x,startY:p.y,startPanX:previewStates[side].x,startPanY:previewStates[side].y};$(e.currentTarget).addClass("dragging");}
function onDrag(e){if(!dragState.active)return;if(e.cancelable)e.preventDefault();const p=getPoint(e),s=previewStates[dragState.side];s.x=dragState.startPanX+(p.x-dragState.startX);s.y=dragState.startPanY+(p.y-dragState.startY);applyPreviewTransform(dragState.side);}
function endDrag(){dragState.active=false;$(".preview-img").removeClass("dragging");}
function getPoint(e){const t=e.touches&&e.touches[0]?e.touches[0]:e;return{x:t.clientX,y:t.clientY};}
function applyPreviewTransform(side){const img=document.querySelector(`.preview-img[data-side="${side}"]`),stage=img?img.closest(".preview-stage"):null,s=previewStates[side];if(stage&&img&&img.naturalWidth){const sw=stage.clientWidth,sh=stage.clientHeight,scale=Math.max(sw/img.naturalWidth,sh/img.naturalHeight),vw=img.naturalWidth*scale*s.zoom,vh=img.naturalHeight*scale*s.zoom,maxX=Math.max(0,(vw-sw)/2),maxY=Math.max(0,(vh-sh)/2);s.x=Math.max(-maxX,Math.min(maxX,s.x));s.y=Math.max(-maxY,Math.min(maxY,s.y));img.style.width=(img.naturalWidth*scale)+"px";img.style.height=(img.naturalHeight*scale)+"px";}$(img).css("transform",`translate(calc(-50% + ${s.x}px), calc(-50% + ${s.y}px)) scale(${s.zoom})`);}
function updatePreviewMeta(){const trimW=toNum($("#widthCm").val(),9),trimH=toNum($("#heightCm").val(),5.4),bleed=toNum($("#bleedCm").val(),.2),r=toNum($("#cornerRadius").val(),0),bleedW=trimW+bleed*2,bleedH=trimH+bleed*2;$(".print-preview").css({"--trim-left":(bleed/bleedW*100).toFixed(4)+"%","--trim-top":(bleed/bleedH*100).toFixed(4)+"%","--trim-width":(trimW/bleedW*100).toFixed(4)+"%","--trim-height":(trimH/bleedH*100).toFixed(4)+"%","--corner-radius":r>0?(r*2.2)+"px":"0px","aspect-ratio":bleedW+" / "+bleedH});$("#metaTrim").text(`${round1(trimW)} x ${round1(trimH)} cm`);$("#metaBleed").text(`${round1(bleedW)} x ${round1(bleedH)} cm`);$("#metaBleedValue").text(`${round1(bleed)} cm`);}
function bindCheckActions(){$("#preflightBtn").on("click",runPreflight);$("#manualCheckBtn").on("click",function(e){e.preventDefault();showManualCheck();});}
function showManualCheck(){$("#lineModalTitle").text("????????");$("#lineModalDesc").text("??????????????????????????????????????????????????????????????????????????????????????????????????????????????????? VIP ???");$("#lineModal .line-card").addClass("manual-check-card");$("#lineModal").addClass("show");}
function runPreflight(){if(!currentFiles.front){showReport("high","尚未上傳正面檔案","請先上傳正面檔案。");return;}const fd=makeFormData("preflight_check");setButtonLoading("#preflightBtn",true,"檢查中");$.ajax({url:"api/preflight_check.php",type:"POST",data:fd,processData:false,contentType:false,dataType:"json"}).done(res=>{if(!res.success){showReport("high","檢查失敗",res.message||"無法檢查檔案。");return;}renderReportFromServer(res.report||{});}).fail(()=>renderLocalCheck("API 無法連線，已顯示前端基本檢查。")).always(()=>setButtonLoading("#preflightBtn",false));}
function renderLocalCheck(extra){$("#preflightReport").addClass("show");$("#checkList").empty();$("#riskBadge").attr("class","risk-badge risk-medium").text("中風險");$("#reportTitle").text("前端基本檢查");$("#reportDesc").text(extra||"這是未送後端的基本檢查結果。");addCommonChecks();$("#suggestedActions").text("可選擇下方工具處理檔案。");scrollReportIntoView();}
function renderReportFromServer(report){$("#preflightReport").addClass("show");$("#checkList").empty();const risk=report.risk_level||"medium";$("#riskBadge").attr("class",`risk-badge risk-${risk}`).text(riskText(risk));$("#reportTitle").text("檔案檢查完成");$("#reportDesc").text(`${report.filename||"檔案"} / ${report.format||"格式"} / ${report.file_size_mb||""} MB`);addCommonChecks();(report.checks||[]).forEach(item=>addCheck(item.label||"檢查",item.text||""));$("#suggestedActions").text((report.suggested_actions||[]).join("、")||"可選擇下方工具處理檔案。");scrollReportIntoView();}
function addCommonChecks(){addCheck("印刷品項",$("#productType option:selected").text());addCheck("印刷面數",$("#printSide option:selected").text()||"單面");addCheck("成品尺寸",`${$("#widthCm").val()} x ${$("#heightCm").val()} cm`);addCheck("出血後尺寸",calcBleedSizeText());addCheck("四邊圓角",$("#cornerRadius option:selected").text());addCheck("DPI",$("#targetDpi").val()+" DPI");}
function bindRepairActions(){$(".repair-card,.repair-tool").on("click",function(){if($(this).data("vip")==="yes"){$("#memberModal").addClass("show");return;}$(".repair-card,.repair-tool").removeClass("active");$(this).addClass("active");currentAction=$(this).data("action");updateCurrentToolName();});$("#processBtn").on("click",submitProcess);updateCurrentToolName();}
function updateCurrentToolName(){const $active=$(".repair-tool.active,.repair-card.active").first();$("#currentToolName").text($active.find("strong").first().text()||"轉印刷檔 PDF");}
function submitProcess(){if(!currentFiles.front){showReport("high","尚未上傳正面檔案","請先上傳正面檔案。");return;}if($("#printSide").val()==="double"&&!currentFiles.back){showReport("medium","缺少背面檔案","目前選擇雙面印刷，請上傳背面檔案，或改成單面印刷。");return;}const fd=makeFormData(currentAction);setButtonLoading("#processBtn",true,"產生中");$.ajax({url:"api/process.php",type:"POST",data:fd,processData:false,contentType:false,dataType:"json"}).done(res=>{if(!res.success){showReport("high","處理失敗",res.message||"系統無法處理此檔案。");return;}$("#preflightReport").addClass("show");$("#checkList").empty();$("#riskBadge").attr("class","risk-badge risk-low").text("已完成");$("#reportTitle").text("檔案生成完成");$("#reportDesc").text(res.message||"檔案已處理完成。");addCommonChecks();if(res.notice)addCheck("備註",res.notice);$("#suggestedActions").text("可下載處理完成檔案。");if(res.download_url)$("#downloadLink").attr("href",res.download_url).removeClass("d-none");scrollReportIntoView();}).fail(()=>showReport("high","處理失敗","無法連線到 api/process.php，請確認 PHP / Imagick 環境。")).always(()=>setButtonLoading("#processBtn",false));}
function makeFormData(action){const fd=new FormData();fd.append("file",currentFiles.front);if($("#printSide").val()==="double"&&currentFiles.back)fd.append("back_file",currentFiles.back);fd.append("print_side",$("#printSide").val());fd.append("action",action);fd.append("width_mm",toNum($("#widthCm").val(),9)*10);fd.append("height_mm",toNum($("#heightCm").val(),5.4)*10);fd.append("bleed_mm",toNum($("#bleedCm").val(),.2)*10);fd.append("corner_radius_mm",toNum($("#cornerRadius").val(),0));fd.append("dpi",$("#targetDpi").val());fd.append("scale",2);fd.append("plate_threshold",245);return fd;}
function addCheck(label,text){$("#checkList").append(`<div class="check-item"><b>${escapeHtml(label)}</b><span>${escapeHtml(text)}</span></div>`);}function scrollReportIntoView(){const el=document.getElementById("preflightReport");if(el&&typeof el.scrollIntoView==="function"){el.scrollIntoView({behavior:"smooth",block:"start"});}}function showReport(level,title,desc){$("#preflightReport").addClass("show");$("#riskBadge").attr("class",`risk-badge risk-${level}`).text(riskText(level));$("#reportTitle").text(title);$("#reportDesc").text(desc);scrollReportIntoView();}function setButtonLoading(selector,isLoading,text){const $btn=$(selector);if(isLoading){$btn.data("origin",$btn.html()).prop("disabled",true).html(`<i class="fa-solid fa-spinner fa-spin"></i> ${text}`);}else{$btn.prop("disabled",false).html($btn.data("origin"));}}
function bindModal(){$("[data-close-modal]").on("click",()=>$("#memberModal").removeClass("show"));$("#memberModal").on("click",function(e){if(e.target===this)$(this).removeClass("show");});$("[data-open-line]").on("click",()=>{$("#lineModalTitle").text("LINE 聯絡客服");$("#lineModalDesc").html("請掃描 QR Code 加入好友，或搜尋 LINE ID：<strong>minwanchun</strong>");$("#lineModal").addClass("show");});$("[data-close-line]").on("click",()=>$("#lineModal").removeClass("show"));$("#lineModal").on("click",function(e){if(e.target===this)$(this).removeClass("show");});}
function isAccountBound(){return localStorage.getItem(accountKey)==="yes";}function openAccountModal(){$("#accountModal").addClass("show");}function closeAccountModal(){$("#accountModal").removeClass("show");}
function bindAccountModalEvents(){$("[data-open-account]").on("click",openAccountModal);$("[data-close-account]").on("click",closeAccountModal);$("#accountModal").on("click",function(e){if(e.target===this)closeAccountModal();});$("[data-bind-provider]").on("click",function(){const provider=$(this).data("bind-provider")||"Email";localStorage.setItem(accountKey,"yes");localStorage.setItem("ai_prepress_login_provider",provider);closeAccountModal();});}
function riskText(level){return level==="low"?"低風險":(level==="high"?"高風險":"中風險");}function calcBleedSizeText(){const w=toNum($("#widthCm").val(),9),h=toNum($("#heightCm").val(),5.4),b=toNum($("#bleedCm").val(),.2);return `${round1(w+b*2)} x ${round1(h+b*2)} cm`;}function getUsageCount(){return Number(localStorage.getItem(usageKey)||"0");}function setUsageCount(v){localStorage.setItem(usageKey,String(v));}function round1(v){return Math.round(Number(v)*10)/10;}function toNum(v,d){const n=Number(v);return Number.isFinite(n)?n:d;}function escapeHtml(str){return String(str).replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));}

function bindPaymentModalEvents(){
  $("[data-open-payment]").on("click",function(){
    const plan=$(this).data("plan-name")||"會員方案";
    $("#paymentPlanName").text(plan);
    $("#paymentModal").addClass("show");
  });
  $("[data-close-payment]").on("click",function(){
    $("#paymentModal").removeClass("show");
  });
  $("#paymentModal").on("click",function(e){
    if(e.target===this)$("#paymentModal").removeClass("show");
  });
  $("#fakePayBtn").on("click",function(){
    localStorage.setItem(accountKey,"yes");
    localStorage.setItem("ai_prepress_fake_paid","yes");
    $(this).text("測試付款成功，會員已開通").prop("disabled",true);
    setTimeout(function(){
      $("#paymentModal").removeClass("show");
      $("#fakePayBtn").text("確認付款並開通測試會員").prop("disabled",false);
    },1200);
  });
}

function bindCoopModalEvents(){
  $("[data-open-coop]").on("click",function(e){
    e.preventDefault();
    $("#coopModal").addClass("show");
  });
  $("[data-close-coop]").on("click",function(){
    $("#coopModal").removeClass("show");
  });
  $("#coopModal").on("click",function(e){
    if(e.target===this)$("#coopModal").removeClass("show");
  });
  $("#coopSubmitBtn").on("click",function(){
    const data={
      company:$("#coopCompany").val()||"",
      name:$("#coopName").val()||"",
      line:$("#coopLine").val()||"",
      email:$("#coopEmail").val()||"",
      type:$("#coopType").val()||"",
      note:$("#coopNote").val()||""
    };
    localStorage.setItem("ai_prepress_coop_lead",JSON.stringify(data));
    $("#coopResult").addClass("show").text("合作資料已送出，我們會再與你聯繫。");
  });
}


/* v27：會員按鈕流程。開始使用 → 綁定帳號 → 付款測試版；若已綁定則直接付款。 */
function openPlanStart(planName) {
  window.aiPrepressPendingPlan = planName || "會員方案";
  if (typeof isAccountBound === "function" && isAccountBound()) {
    openPaymentModal(window.aiPrepressPendingPlan);
    return;
  }
  openAccountModal();
}

function openPaymentModal(planName) {
  $("#paymentPlanName").text(planName || "會員方案");
  $("#paymentModal").addClass("show");
}

function bindV27PlanFlow() {
  $("[data-start-plan]").off("click.v27").on("click.v27", function (e) {
    e.preventDefault();
    openPlanStart($(this).data("plan-name") || "會員方案");
  });
  $("[data-open-payment]").off("click.v27").on("click.v27", function (e) {
    e.preventDefault();
    openPlanStart($(this).data("plan-name") || "會員方案");
  });
  $("[data-bind-provider]").off("click.v27").on("click.v27", function () {
    const provider = $(this).data("bind-provider") || "Email";
    localStorage.setItem(accountKey, "yes");
    localStorage.setItem("ai_prepress_login_provider", provider);
    closeAccountModal();
    if (window.aiPrepressPendingPlan) {
      openPaymentModal(window.aiPrepressPendingPlan);
    }
  });
  $("[data-close-payment]").off("click.v27").on("click.v27", function () {
    $("#paymentModal").removeClass("show");
  });
  $("#paymentModal").off("click.v27").on("click.v27", function (e) {
    if (e.target === this) $("#paymentModal").removeClass("show");
  });
  $("#fakePayBtn").off("click.v27").on("click.v27", function () {
    localStorage.setItem(accountKey, "yes");
    localStorage.setItem("ai_prepress_fake_paid", "yes");
    $(this).text("測試付款成功，會員已開通").prop("disabled", true);
    setTimeout(function () {
      $("#paymentModal").removeClass("show");
      $("#fakePayBtn").text("確認付款並開通測試會員").prop("disabled", false);
    }, 1200);
  });
}

/* v27：合作洽談只從頁尾聯絡區開啟，表單資料先暫存在瀏覽器。 */
function bindV27CoopFlow() {
  $("[data-open-coop]").off("click.v27").on("click.v27", function (e) {
    e.preventDefault();
    $("#coopModal").addClass("show");
  });
  $("[data-close-coop]").off("click.v27").on("click.v27", function () {
    $("#coopModal").removeClass("show");
  });
  $("#coopModal").off("click.v27").on("click.v27", function (e) {
    if (e.target === this) $("#coopModal").removeClass("show");
  });
  $("#coopSubmitBtn").off("click.v27").on("click.v27", function () {
    const data = {
      company: $("#coopCompany").val() || "",
      name: $("#coopName").val() || "",
      line: $("#coopLine").val() || "",
      email: $("#coopEmail").val() || "",
      note: $("#coopNote").val() || ""
    };
    localStorage.setItem("ai_prepress_coop_lead", JSON.stringify(data));
    $("#coopResult").addClass("show").text("合作資料已送出，我們會再與你聯繫。");
  });
}
$(function(){bindV27PlanFlow();bindV27CoopFlow();});



/* v28 standalone pages */
$(function(){
  const params = new URLSearchParams(window.location.search);
  const plan = params.get("plan");
  if (plan === "starter") $("#paymentPlanName").text("效率會員 NT$149/月");
  if (plan === "vip") $("#paymentPlanName").text("企業 VIP NT$399/月");

  $("#fakePayPageBtn").off("click.v28").on("click.v28", function(){
    localStorage.setItem("ai_prepress_account_bound_v23", "yes");
    localStorage.setItem("ai_prepress_fake_paid", "yes");
    $("#paymentPageResult").addClass("show");
  });

  $("#coopSubmitBtn").off("click.v28").on("click.v28", function(){
    const data = {
      company: $("#coopCompany").val() || "",
      name: $("#coopName").val() || "",
      line: $("#coopLine").val() || "",
      email: $("#coopEmail").val() || "",
      note: $("#coopNote").val() || ""
    };
    localStorage.setItem("ai_prepress_coop_lead", JSON.stringify(data));
    $("#coopResult").addClass("show").text("合作資料已送出，我們會再與你聯繫。");
  });
});
