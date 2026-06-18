const printProducts={"business-card":{w:9,h:5.4,bleed:.2,img:"skin/img/print/business-card.png",defaultOrientation:"landscape"},"a4dm":{w:21,h:29.7,bleed:.2,img:"skin/img/print/a4-dm.png",defaultOrientation:"portrait"},"a3dm":{w:29.7,h:42,bleed:.2,img:"skin/img/print/large-format.png",defaultOrientation:"portrait"},"sticker-card":{w:9,h:5.4,bleed:.3,img:"skin/img/print/sticker.png",defaultOrientation:"landscape"},"postcard":{w:10.5,h:14.8,bleed:.2,img:"skin/img/print/postcard.png",defaultOrientation:"portrait"},"custom":{w:9,h:9,bleed:.2,img:"skin/img/print/package.png",defaultOrientation:"landscape"}};
let baseSize={w:9,h:5.4},currentFiles={front:null,back:null},currentAction="print_ready",currentOrientation="landscape",previewStates={front:{zoom:1,x:0,y:0},back:{zoom:1,x:0,y:0}},dragState={active:false,side:"front",startX:0,startY:0,startPanX:0,startPanY:0};
const freePdfLimit=2,usageKey="ai_prepress_pdf_generated_count_v24",accountKey="ai_prepress_account_bound_v24";
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
function showManualCheck(){const lang=localStorage.getItem('ai_prepress_lang')||'zh-Hant';const t=prepressI18n[lang];$("#lineModalTitle").text(t?t.manualTitle:"建議專業人工檢查");$("#lineModalDesc").text(t?t.manualDesc:"文字多、字級小、指定特殊色、需要刀模、材質屬性、燙金、白墨、局部光、折線、加工、包裝盒或書籍等重要印件，如果沒有把握檔案是否符合印刷廠的眾多退件條件需求，建議還是交由專業的完稿人員檢查，線上檢查只能滿足一般制式的合版印刷，詳情可連繫客服諮詢或加入VIP會員。");$("#lineModal .line-card").addClass("manual-check-card");$("#lineModal").addClass("show");}
function runPreflight(){if(!currentFiles.front){showReport("high","尚未上傳正面檔案","請先上傳正面檔案。");return;}const fd=makeFormData("preflight_check");setButtonLoading("#preflightBtn",true,"檢查中");$.ajax({url:"api/preflight_check.php",type:"POST",data:fd,processData:false,contentType:false,dataType:"json"}).done(res=>{if(!res.success){showReport("high","檢查失敗",res.message||"無法檢查檔案。");return;}renderReportFromServer(res.report||{});}).fail(()=>renderLocalCheck("API 無法連線，已顯示前端基本檢查。")).always(()=>setButtonLoading("#preflightBtn",false));}
function renderLocalCheck(extra){$("#preflightReport").addClass("show");$("#checkList").empty();$("#riskBadge").attr("class","risk-badge risk-medium").text("中風險");$("#reportTitle").text("前端基本檢查");$("#reportDesc").text(extra||"這是未送後端的基本檢查結果。");addCommonChecks();$("#suggestedActions").text("可選擇下方工具處理檔案。");}
function renderReportFromServer(report){$("#preflightReport").addClass("show");$("#checkList").empty();const risk=report.risk_level||"medium";$("#riskBadge").attr("class",`risk-badge risk-${risk}`).text(riskText(risk));$("#reportTitle").text("檔案檢查完成");$("#reportDesc").text(`${report.filename||"檔案"} / ${report.format||"格式"} / ${report.file_size_mb||""} MB`);addCommonChecks();(report.checks||[]).forEach(item=>addCheck(item.label||"檢查",item.text||""));$("#suggestedActions").text((report.suggested_actions||[]).join("、")||"可選擇下方工具處理檔案。");}
function addCommonChecks(){addCheck("印刷品項",$("#productType option:selected").text());addCheck("印刷面數",$("#printSide option:selected").text()||"單面");addCheck("成品尺寸",`${$("#widthCm").val()} x ${$("#heightCm").val()} cm`);addCheck("出血後尺寸",calcBleedSizeText());addCheck("四邊圓角",$("#cornerRadius option:selected").text());addCheck("DPI",$("#targetDpi").val()+" DPI");}
function bindRepairActions(){$(".repair-card,.repair-tool").on("click",function(){if($(this).data("vip")==="yes"){$("#memberModal").addClass("show");return;}$(".repair-card,.repair-tool").removeClass("active");$(this).addClass("active");currentAction=$(this).data("action");updateCurrentToolName();});$("#processBtn").on("click",submitProcess);updateCurrentToolName();}
function updateCurrentToolName(){const $active=$(".repair-tool.active,.repair-card.active").first();$("#currentToolName").text($active.find("strong").first().text()||"轉印刷檔 PDF");}
function submitProcess(){if(!isAccountBound()){openAccountModal();return;}if(!currentFiles.front){showReport("high","尚未上傳正面檔案","請先上傳正面檔案。");return;}if($("#printSide").val()==="double"&&!currentFiles.back){showReport("medium","缺少背面檔案","目前選擇雙面印刷，請上傳背面檔案，或改成單面印刷。");return;}const isMain=currentAction==="print_ready",used=getUsageCount();if(isMain&&used>=freePdfLimit){$("#memberModal").addClass("show");return;}const fd=makeFormData(currentAction);setButtonLoading("#processBtn",true,"產生中");$.ajax({url:"api/process.php",type:"POST",data:fd,processData:false,contentType:false,dataType:"json"}).done(res=>{if(!res.success){showReport("high","處理失敗",res.message||"系統無法處理此檔案。");return;}if(isMain)setUsageCount(used+1);$("#preflightReport").addClass("show");$("#checkList").empty();$("#riskBadge").attr("class","risk-badge risk-low").text("已完成");$("#reportTitle").text("檔案生成完成");$("#reportDesc").text(res.message||"檔案已處理完成。");addCommonChecks();if(res.notice)addCheck("備註",res.notice);$("#suggestedActions").text("可下載處理完成檔案。");if(res.download_url)$("#downloadLink").attr("href",res.download_url).removeClass("d-none");}).fail(()=>showReport("high","處理失敗","無法連線到 api/process.php，請確認 PHP / ImageMagick 環境。")).always(()=>setButtonLoading("#processBtn",false));}
function makeFormData(action){const fd=new FormData();fd.append("file",currentFiles.front);if($("#printSide").val()==="double"&&currentFiles.back)fd.append("back_file",currentFiles.back);fd.append("print_side",$("#printSide").val());fd.append("action",action);fd.append("width_mm",toNum($("#widthCm").val(),9)*10);fd.append("height_mm",toNum($("#heightCm").val(),5.4)*10);fd.append("bleed_mm",toNum($("#bleedCm").val(),.2)*10);fd.append("corner_radius_mm",toNum($("#cornerRadius").val(),0));fd.append("dpi",$("#targetDpi").val());fd.append("scale",2);fd.append("plate_threshold",245);return fd;}
function addCheck(label,text){$("#checkList").append(`<div class="check-item"><b>${escapeHtml(label)}</b><span>${escapeHtml(text)}</span></div>`);}function showReport(level,title,desc){$("#preflightReport").addClass("show");$("#riskBadge").attr("class",`risk-badge risk-${level}`).text(riskText(level));$("#reportTitle").text(title);$("#reportDesc").text(desc);}function setButtonLoading(selector,isLoading,text){const $btn=$(selector);if(isLoading){$btn.data("origin",$btn.html()).prop("disabled",true).html(`<i class="fa-solid fa-spinner fa-spin"></i> ${text}`);}else{$btn.prop("disabled",false).html($btn.data("origin"));}}
function bindModal(){$("[data-close-modal]").on("click",()=>$("#memberModal").removeClass("show"));$("#memberModal").on("click",function(e){if(e.target===this)$(this).removeClass("show");});$("[data-open-line]").on("click",()=>{$("#lineModalTitle").text("LINE 聯絡客服");$("#lineModalDesc").html("請掃描 QR Code 加入好友，或搜尋 LINE ID：<strong>minwanchun</strong>");$("#lineModal").addClass("show");});$("[data-close-line]").on("click",()=>$("#lineModal").removeClass("show"));$("#lineModal").on("click",function(e){if(e.target===this)$(this).removeClass("show");});}
function isAccountBound(){return localStorage.getItem(accountKey)==="yes";}function openAccountModal(){$("#accountModal").addClass("show");}function closeAccountModal(){$("#accountModal").removeClass("show");}
function bindAccountModalEvents(){$("[data-open-account]").on("click",openAccountModal);$("[data-close-account]").on("click",closeAccountModal);$("#accountModal").on("click",function(e){if(e.target===this)closeAccountModal();});$("[data-bind-provider]").on("click",function(){const provider=$(this).data("bind-provider")||"Email";localStorage.setItem(accountKey,"yes");localStorage.setItem("ai_prepress_login_provider",provider);closeAccountModal();});}
function riskText(level){return level==="low"?"低風險":(level==="high"?"高風險":"中風險");}function calcBleedSizeText(){const w=toNum($("#widthCm").val(),9),h=toNum($("#heightCm").val(),5.4),b=toNum($("#bleedCm").val(),.2);return `${round1(w+b*2)} x ${round1(h+b*2)} cm`;}function getUsageCount(){return Number(localStorage.getItem(usageKey)||"0");}function setUsageCount(v){localStorage.setItem(usageKey,String(v));}function round1(v){return Math.round(Number(v)*10)/10;}function toNum(v,d){const n=Number(v);return Number.isFinite(n)?n:d;}function escapeHtml(str){return String(str).replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));}

// v25 多國語言切換：支援繁中 / 英文 / 日文，主要針對首頁核心流程。
const prepressI18n={
  en:{
    nav:['Product & Size','Upload Preview','File Check','Create PDF','Plans','Pricing'],
    heroTitle:'Create print-ready PDF in one click',
    heroSubtitle:'Still struggling with AI images or JPG files that cannot be printed? Upload your image to convert AI / JPG / PNG / WebP / GIF / SVG into print-ready files, run online preflight checks, add bleed and crop marks, convert RGB to CMYK, and request printing quotes. Ideal for AI-generated artwork, Canva designs, business cards, flyers, stickers, and postcards before production.',
    step1:'Select product and size', step2:'Upload files and preview', step3:'Check file', step4:'Choose a tool and create file',
    front:'Front', back:'Back', uploadFront:'Upload front file', uploadBack:'Upload back file', drag:'Drag or click to select JPG / PNG / WebP / GIF / SVG',
    onlineCheck:'Online file check', manualCheck:'Professional manual check', createFile:'Create file', quote:'Request printing quote',
    recommended:'Recommended workflow', freeTools:'Free image tools', vip:'VIP / Pro', current:'Current tool:', download:'Download processed file',
    lineTitle:'Contact us on LINE', lineDesc:'Scan the QR Code or search LINE ID: <strong>minwanchun</strong>',
    manualTitle:'Professional manual check recommended',
    manualDesc:'For files with lots of text, small type, spot colors, dielines, material requirements, foil stamping, white ink, spot UV, folding lines, finishing, packaging, books, or important print jobs, we recommend professional prepress review. Online checks only cover standard gang-run printing rules. Contact support or join VIP for help.'
  },
  ja:{
    nav:['商品・サイズ','アップロード確認','データ確認','PDF作成','料金プラン','印刷見積'],
    heroTitle:'印刷用PDFをワンクリックで作成',
    heroSubtitle:'AI画像やJPGデータが印刷に使えるか不安ですか？画像をアップロードすると、AI / JPG / PNG / WebP / GIF / SVG を印刷用データに変換し、オンライン入稿チェック、塗り足し・トンボ、RGBからCMYK、印刷見積まで確認できます。AI生成画像、Canva原稿、名刺、DM、ステッカー、ポストカードの印刷前整理に最適です。',
    step1:'商品とサイズを選択', step2:'ファイルをアップロードして確認', step3:'ファイルチェック', step4:'ツールを選んで作成',
    front:'表面', back:'裏面', uploadFront:'表面ファイルをアップロード', uploadBack:'裏面ファイルをアップロード', drag:'JPG / PNG / WebP / GIF / SVG をドラッグまたは選択',
    onlineCheck:'オンラインチェック', manualCheck:'専門スタッフ確認', createFile:'ファイル作成', quote:'印刷見積へ',
    recommended:'おすすめフロー', freeTools:'無料画像ツール', vip:'VIP / 専門', current:'現在の選択:', download:'処理済みファイルをダウンロード',
    lineTitle:'LINEで相談', lineDesc:'QRコードを読み取るか、LINE ID：<strong>minwanchun</strong> を検索してください。',
    manualTitle:'専門スタッフ確認をおすすめします',
    manualDesc:'文字が多い、小さい文字、特色指定、型抜き、素材指定、箔押し、白インク、スポットUV、折り線、加工、パッケージ、書籍など重要な印刷物は、専門のプリプレス確認をおすすめします。オンラインチェックは標準的な合版印刷ルールのみ対応します。詳細はLINEまたはVIP会員でご相談ください。'
  }
};
function setLanguage(lang){
  localStorage.setItem('ai_prepress_lang',lang);
  const t=prepressI18n[lang];
  if(!t)return;
  document.documentElement.lang=lang;
  const nav=document.querySelectorAll('.nav-menu a'); t.nav.forEach((v,i)=>{if(nav[i])nav[i].textContent=v;});
  $('#heroTitle').text(t.heroTitle); $('#heroSubtitle').text(t.heroSubtitle);
  $('#step1 h2').text(t.step1); $('#step2 h2').text(t.step2); $('#step3 h2').text(t.step3); $('#step4 h2').text(t.step4);
  $('[data-side-box="front"] .side-title span').text(t.front); $('[data-side-box="back"] .side-title span').text(t.back);
  $('[data-side="front"] strong').text(t.uploadFront); $('[data-side="back"] strong').text(t.uploadBack); $('.dropzone-side span').first().text(t.drag);
  $('#preflightBtn').html('<i class="fa-solid fa-clipboard-check"></i> '+t.onlineCheck); $('#manualCheckBtn').html('<i class="fa-solid fa-user-gear"></i> '+t.manualCheck);
  $('#processBtn').html('<i class="fa-solid fa-gears"></i> '+t.createFile); $('.selected-tool-bar .btnx-light').text(t.quote);
  $('.recommended-panel .tool-panel-title span').text(t.recommended); $('.tool-panel:not(.recommended-panel):not(.vip-panel) .tool-panel-title span').text(t.freeTools); $('.vip-panel .tool-panel-title span').text(t.vip);
  $('.selected-tool-bar > span').text(t.current); $('#downloadLink').html('<i class="fa-solid fa-download"></i> '+t.download);
}
function bindLanguage(){
  const saved=localStorage.getItem('ai_prepress_lang')||'zh-Hant';
  $('#langSelect').val(saved);
  if(saved!=='zh-Hant')setLanguage(saved);
  $('#langSelect').off('change.v25').on('change.v25',function(){const lang=this.value;if(lang==='zh-Hant'){localStorage.setItem('ai_prepress_lang','zh-Hant');location.reload();return;}setLanguage(lang);});
}
$(bindLanguage);


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
