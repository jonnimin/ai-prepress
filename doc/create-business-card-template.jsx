var mm = 72 / 25.4;

var bleed = 3 * mm;
var trimW = 90 * mm;
var trimH = 54 * mm;
var docW = trimW + bleed * 2;
var docH = trimH + bleed * 2;

var doc = app.documents.add(DocumentColorSpace.CMYK, docW, docH);
doc.rulerUnits = RulerUnits.Millimeters;

function cmyk(c, m, y, k) {
  var color = new CMYKColor();
  color.cyan = c;
  color.magenta = m;
  color.yellow = y;
  color.black = k;
  return color;
}

function makeLayer(name) {
  var layer = doc.layers.add();
  layer.name = name;
  return layer;
}

function rect(layer, name, x, y, w, h, stroke, fill, strokeWidth) {
  var item = layer.pathItems.rectangle(-y, x, w, h);
  item.name = name;
  item.stroked = !!stroke;
  item.filled = !!fill;
  if (stroke) item.strokeColor = stroke;
  if (fill) item.fillColor = fill;
  item.strokeWidth = strokeWidth || 0.5;
  return item;
}

function line(layer, name, x1, y1, x2, y2, stroke, strokeWidth) {
  var item = layer.pathItems.add();
  item.name = name;
  item.setEntirePath([[x1, -y1], [x2, -y2]]);
  item.stroked = true;
  item.filled = false;
  item.strokeColor = stroke;
  item.strokeWidth = strokeWidth || 0.35;
  return item;
}

function text(layer, name, contents, x, y, size, color) {
  var item = layer.textFrames.add();
  item.name = name;
  item.contents = contents;
  item.position = [x, -y];
  item.textRange.characterAttributes.size = size;
  item.textRange.characterAttributes.fillColor = color || cmyk(0, 0, 0, 90);
  return item;
}

var artwork = makeLayer("Artwork");
var placeholder = makeLayer("Editable Placeholders");
var guide = makeLayer("Guides - Bleed / Trim / Safe Area");
guide.locked = false;

var lightCyan = cmyk(35, 0, 0, 0);
var magenta = cmyk(0, 85, 0, 0);
var gray = cmyk(0, 0, 0, 45);
var richBlack = cmyk(60, 50, 50, 100);
var pale = cmyk(4, 2, 2, 0);

rect(artwork, "Background with full bleed", 0, 0, docW, docH, null, pale, 0);
rect(artwork, "Accent block", docW - 30 * mm, 0, 30 * mm, docH, null, cmyk(70, 10, 0, 0), 0);
rect(artwork, "Logo placeholder", 10 * mm, 10 * mm, 18 * mm, 18 * mm, richBlack, null, 0.7);

text(placeholder, "Name", "YOUR NAME", 10 * mm, 36 * mm, 15, richBlack);
text(placeholder, "Title", "Title / Company", 10 * mm, 42 * mm, 7, cmyk(0, 0, 0, 70));
text(placeholder, "Contact", "email@example.com  |  +886 900 000 000", 10 * mm, 49 * mm, 6.5, cmyk(0, 0, 0, 75));
text(placeholder, "Website", "www.example.com", 10 * mm, 53 * mm, 6.5, cmyk(0, 0, 0, 75));
text(placeholder, "Logo text", "LOGO", 13 * mm, 21 * mm, 7, cmyk(0, 0, 0, 65));

rect(guide, "Bleed size 96 x 60 mm", 0, 0, docW, docH, lightCyan, null, 0.5);
rect(guide, "Trim size 90 x 54 mm", bleed, bleed, trimW, trimH, magenta, null, 0.6);
rect(guide, "Safe area 84 x 48 mm", bleed + 3 * mm, bleed + 3 * mm, trimW - 6 * mm, trimH - 6 * mm, gray, null, 0.4);

var mark = 4 * mm;
line(guide, "Crop mark top left horizontal", bleed - mark, bleed, bleed - 0.8 * mm, bleed, gray, 0.35);
line(guide, "Crop mark top left vertical", bleed, bleed - mark, bleed, bleed - 0.8 * mm, gray, 0.35);
line(guide, "Crop mark top right horizontal", bleed + trimW + 0.8 * mm, bleed, bleed + trimW + mark, bleed, gray, 0.35);
line(guide, "Crop mark top right vertical", bleed + trimW, bleed - mark, bleed + trimW, bleed - 0.8 * mm, gray, 0.35);
line(guide, "Crop mark bottom left horizontal", bleed - mark, bleed + trimH, bleed - 0.8 * mm, bleed + trimH, gray, 0.35);
line(guide, "Crop mark bottom left vertical", bleed, bleed + trimH + 0.8 * mm, bleed, bleed + trimH + mark, gray, 0.35);
line(guide, "Crop mark bottom right horizontal", bleed + trimW + 0.8 * mm, bleed + trimH, bleed + trimW + mark, bleed + trimH, gray, 0.35);
line(guide, "Crop mark bottom right vertical", bleed + trimW, bleed + trimH + 0.8 * mm, bleed + trimW, bleed + trimH + mark, gray, 0.35);

text(guide, "Guide note", "Bleed 3 mm | Trim 90 x 54 mm | Safe margin 3 mm", 6 * mm, docH - 2 * mm, 5.5, gray);
guide.locked = true;

for (var i = doc.layers.length - 1; i >= 0; i--) {
  if (doc.layers[i].name === "Layer 1" && doc.layers.length > 1) {
    doc.layers[i].remove();
  }
}

var outFile = new File("D:/TOOL/ai-printing/doc/business-card-template.ai");
var options = new IllustratorSaveOptions();
options.compatibility = Compatibility.ILLUSTRATOR17;
options.pdfCompatible = true;
options.embedICCProfile = true;
doc.saveAs(outFile, options);
