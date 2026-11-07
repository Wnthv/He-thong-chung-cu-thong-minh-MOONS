import json, subprocess, os
OUT = '/home/claude/diagrams'; os.makedirs(OUT, exist_ok=True)
F = 'DejaVu Sans'
G = '#5F7B6F'

def render(name, dot):
    open(f'{OUT}/{name}.dot', 'w').write(dot)
    subprocess.run(['dot', '-Tpng', '-Gdpi=170', f'{OUT}/{name}.dot', '-o', f'{OUT}/{name}.png'], check=True)

def esc(s): return s.replace('"', '\\"')

# ---------------- Kiến trúc ----------------
render('kien_truc', f'''digraph G {{ rankdir=LR; fontname="{F}"; node[fontname="{F}",fontsize=11,shape=box,style="rounded,filled",fillcolor="#F2F6F3",color="{G}"]; edge[fontname="{F}",fontsize=9,color="#555555"];
subgraph cluster_c {{label="Client (trình duyệt)";style="rounded,dashed";color="#999999";fontname="{F}";
 m[label="Mobile Web – Cư dân\\n(Chủ hộ, Khách thuê)"]; d[label="Desktop Web – Ban Quản lý\\n(Lễ tân, Kế toán, KTT, ĐPV, KTV, Admin)"];}}
subgraph cluster_s {{label="Máy chủ Web – PHP 8 (Apache/WAMP)";style="rounded";color="{G}";fontname="{F}";
 fc[label="Front Controller\\npublic/index.php\\n(Router + RBAC + CSRF)",fillcolor="#E7EEEA"];
 c[label="Controllers\\nAuth, Resident, Payment, Admin"]; s[label="Services (nghiệp vụ)\\nBilling, Payment, Booking, Vehicle,\\nResident, Ticket, AI, Report, Audit"];
 v[label="Views (PHP template)\\nlayouts/resident, layouts/admin"]; fc->c; c->s; c->v; }}
db[label="MySQL 8 / MariaDB\\n15 bảng InnoDB\\nCHECK · UNIQUE · FK · 16 TRIGGER",shape=cylinder,fillcolor="#FFF7E6",color="#B7791F"];
vn[label="Cổng VNPay\\n(HMAC SHA512, IPN)",fillcolor="#E6F0FA",color="#005BAA"]; gm[label="Google Gemini API\\n(tùy chọn)",fillcolor="#E6F0FA",color="#005BAA"];
m->fc[label="HTTPS"]; d->fc[label="HTTPS"]; s->db[label="PDO\\nprepared statement"]; s->vn[label="URL thanh toán"]; vn->fc[label="IPN / ReturnUrl"]; s->gm[label="ngữ cảnh RAG"];
}}''')

# ---------------- BPMN ----------------
def bpmn(name, lanes, flows, title=''):
    s = [f'digraph G {{ rankdir=LR; compound=true; newrank=true; fontname="{F}"; label="{title}"; labelloc=t; fontsize=13; nodesep=0.35; ranksep=0.35;',
         f'node[fontname="{F}",fontsize=10]; edge[fontname="{F}",fontsize=9,color="#444444",arrowsize=0.7];']
    for i, (ln, nodes) in enumerate(lanes):
        s.append(f'subgraph cluster_{i} {{ label="{ln}"; labeljust=l; style=filled; fillcolor="{"#F7F8F6" if i % 2 else "#EEF3F0"}"; color="#99AAAA";')
        for nid, kind, lab in nodes:
            if kind == 'start': s.append(f'{nid}[shape=circle,label="",width=0.3,style=filled,fillcolor="#C6F6D5",color="#2F855A"];')
            elif kind == 'end': s.append(f'{nid}[shape=doublecircle,label="",width=0.25,style=filled,fillcolor="#FED7D7",color="#C53030"];')
            elif kind == 'gw': s.append(f'{nid}[shape=diamond,label="{esc(lab)}",style=filled,fillcolor="#FEFCBF",color="#B7791F",fontsize=9,width=0.6,height=0.6];')
            elif kind == 'data': s.append(f'{nid}[shape=note,label="{esc(lab)}",style=filled,fillcolor="#FFFFFF",color="#888888",fontsize=9];')
            else: s.append(f'{nid}[shape=box,style="rounded,filled",fillcolor="#FFFFFF",color="{G}",label="{esc(lab)}"];')
        s.append('}')
    for f in flows:
        a, b = f[0], f[1]; lab = f[2] if len(f) > 2 else ''
        s.append(f'{a}->{b}[label="{esc(lab)}"{",style=dashed" if lab.startswith("~") else ""}];'.replace('label="~', 'label="'))
    s.append('}')
    render(name, '\n'.join(s))

bpmn('bpmn_asis_the_xe', [
    ('Cư dân', [('s', 'start', ''), ('a1', 't', 'Lấy mẫu đơn\\ntại quầy'), ('a2', 't', 'Điền đơn giấy,\\nphotocopy cà vẹt'), ('a6', 't', 'Đến quầy hỏi\\ntiến độ (nhiều lần)'), ('e', 'end', '')]),
    ('Lễ tân', [('b1', 't', 'Nhận đơn giấy'), ('b2', 't', 'Tra Excel đếm\\nsố xe của căn hộ'), ('g', 'gw', 'Hợp lệ?'), ('b3', 't', 'Gọi điện báo\\nnộp lại từ đầu'), ('b4', 't', 'Ghi vào Excel,\\nphát thẻ')]),
    ('Kế toán', [('c1', 't', 'Nhập tay phí xe\\nvào file hóa đơn')])],
    [('s', 'a1'), ('a1', 'a2'), ('a2', 'b1'), ('b1', 'b2'), ('b2', 'g'), ('g', 'b3', 'Không'), ('b3', 'a2'), ('g', 'b4', 'Có'), ('b4', 'c1', '~Email/Zalo'), ('a2', 'a6', '~chờ'), ('b4', 'e')],
    'AS-IS: Đăng ký thẻ xe thủ công')
bpmn('bpmn_asis_thu_phi', [
    ('Kế toán', [('s', 'start', ''), ('k1', 't', 'Đi ghi chỉ số\\nđồng hồ (sổ tay)'), ('k2', 't', 'Nhập Excel,\\ntự tính bậc điện'), ('k3', 't', 'In & dán thông báo\\nphí ở sảnh / gửi Zalo'), ('k5', 't', 'Đối chiếu sao kê\\nngân hàng thủ công'), ('k6', 't', 'Đánh dấu đã thu\\ntrên Excel'), ('e', 'end', '')]),
    ('Cư dân', [('r1', 't', 'Xem thông báo\\ndán ở sảnh'), ('g', 'gw', 'Hình thức?'), ('r2', 't', 'Chuyển khoản,\\nghi nội dung tự do'), ('r3', 't', 'Nộp tiền mặt\\ntại quầy')])],
    [('s', 'k1'), ('k1', 'k2'), ('k2', 'k3'), ('k3', 'r1'), ('r1', 'g'), ('g', 'r2', 'CK'), ('g', 'r3', 'Tiền mặt'), ('r2', 'k5'), ('r3', 'k6'), ('k5', 'k6'), ('k6', 'e')],
    'AS-IS: Tính phí và thu phí thủ công')
bpmn('bpmn_tobe_the_xe', [
    ('Cư dân', [('s', 'start', ''), ('a1', 't', 'Nhập biển số, loại xe,\\ntải ảnh cà vẹt'), ('a2', 't', 'Bổ sung hồ sơ\\n(giữ lịch sử)'), ('a3', 't', 'Nhận thông báo,\\nnhận thẻ tại quầy'), ('e', 'end', '')]),
    ('Hệ thống', [('h1', 'gw', 'Đúng định dạng,\\nchưa trùng,\\ncòn định mức?'), ('h2', 't', 'Lưu hồ sơ\\nChờ duyệt'), ('h3', 't', 'Khóa dòng căn hộ,\\nkiểm tra lại định mức'), ('h4', 't', 'Đổi trạng thái Đã duyệt,\\nlưu người duyệt'), ('h5', 't', 'Tự cộng phí gửi xe\\nvào kỳ hóa đơn sau')]),
    ('Lễ tân', [('b1', 't', 'Đối chiếu ảnh cà vẹt'), ('g', 'gw', 'Quyết định'), ('b2', 't', 'Nạp mã RFID,\\ncấp thẻ')])],
    [('s', 'a1'), ('a1', 'h1'), ('h1', 'a1', 'Không'), ('h1', 'h2', 'Có'), ('h2', 'b1'), ('b1', 'g'), ('g', 'a2', 'Yêu cầu bổ sung'), ('a2', 'h2'), ('g', 'h3', 'Duyệt'), ('h3', 'h4'), ('h4', 'b2'), ('h4', 'h5'), ('b2', 'a3'), ('a3', 'e')],
    'TO-BE: Đăng ký và duyệt thẻ xe trực tuyến')
bpmn('bpmn_tobe_hoa_don', [
    ('Kế toán', [('s', 'start', ''), ('k1', 't', 'Nhập chỉ số\\nhàng loạt theo tháp'), ('k2', 't', 'Ghi chú xác minh\\ncông tơ'), ('k3', 't', 'Lập hóa đơn\\nhàng loạt')]),
    ('Hệ thống', [('h1', 'gw', 'Mới ≥ cũ và\\n≤ 3×TB 3 kỳ?'), ('h2', 't', 'Tính điện 6 bậc, nước,\\nphí quản lý, phí xe'), ('h3', 't', 'HĐ Chờ duyệt'), ('h4', 't', 'Phát hành, khóa sổ\\nchỉ số'), ('h5', 'gw', 'IPN hợp lệ?\\n(chữ ký, số tiền)'), ('h6', 't', 'Gạch nợ tự động'), ('h7', 't', 'Ngoại lệ đối soát')]),
    ('Kế toán trưởng', [('t1', 'gw', 'Phê duyệt?'), ('t2', 't', 'Hủy kèm lý do,\\nmở khóa chỉ số')]),
    ('Cư dân / VNPay', [('r1', 't', 'Xem hóa đơn,\\nthanh toán VNPay'), ('e', 'end', '')])],
    [('s', 'k1'), ('k1', 'h1'), ('h1', 'k2', 'Bất thường'), ('k2', 'h2'), ('h1', 'h2', 'Hợp lệ'), ('k3', 'h2'), ('h2', 'h3'), ('h3', 't1'), ('t1', 'h4', 'Duyệt'), ('t1', 't2', 'Sai sót'), ('t2', 'k1'), ('h4', 'r1'), ('r1', 'h5'), ('h5', 'h6', 'Có'), ('h5', 'h7', 'Không'), ('h6', 'e')],
    'TO-BE: Lập hóa đơn, phê duyệt và thanh toán')
bpmn('bpmn_tobe_tien_ich', [
    ('Cư dân', [('s', 'start', ''), ('a1', 't', 'Chọn tiện ích,\\nngày, khung giờ'), ('a2', 't', 'Nhận vé QR'), ('a3', 't', 'Xuất trình vé'), ('e', 'end', '')]),
    ('Hệ thống', [('h0', 't', 'SELECT … FOR UPDATE\\n(khóa dòng tiện ích)'), ('h1', 'gw', 'Nợ >30 ngày? Hạn mức tuần?\\nSức chứa? Trùng giờ?'), ('h2', 't', 'Hoàn tác, báo lý do'), ('h3', 't', 'Lưu lịch, sinh mã vé')]),
    ('Lễ tân', [('b1', 't', 'Quét QR, ghi nhận\\nĐã sử dụng')])],
    [('s', 'a1'), ('a1', 'h0'), ('h0', 'h1'), ('h1', 'h2', 'Vi phạm'), ('h2', 'a1'), ('h1', 'h3', 'Hợp lệ'), ('h3', 'a2'), ('a2', 'a3'), ('a3', 'b1'), ('b1', 'e')],
    'TO-BE: Đặt tiện ích nội khu')
bpmn('bpmn_tobe_su_co', [
    ('Cư dân', [('s', 'start', ''), ('a1', 't', 'Gửi phiếu kèm\\nảnh hiện trường'), ('a2', 'gw', 'Hài lòng?'), ('a3', 't', 'Chấm 1–5 sao'), ('e', 'end', '')]),
    ('Điều phối viên', [('d1', 'gw', 'Hợp lệ?'), ('d2', 't', 'Phân công KTV\\n(khẩn cấp ≤ 15 phút)'), ('d3', 't', 'Từ chối kèm lý do')]),
    ('Kỹ thuật viên', [('k1', 't', 'Đang xử lý'), ('k2', 't', 'Chờ linh kiện'), ('k3', 't', 'Gửi nghiệm thu\\n(bắt buộc ảnh)')])],
    [('s', 'a1'), ('a1', 'd1'), ('d1', 'd3', 'Không'), ('d1', 'd2', 'Có'), ('d2', 'k1'), ('k1', 'k2', '~thiếu vật tư'), ('k2', 'k1'), ('k1', 'k3'), ('k3', 'a2'), ('a2', 'a3', 'Có'), ('a2', 'k1', 'Sửa lại'), ('a3', 'e')],
    'TO-BE: Tiếp nhận và xử lý sự cố')

# ---------------- Use Case ----------------
def uc(name, actors, system, cases, links, extra=''):
    s = [f'digraph G {{ rankdir=LR; fontname="{F}"; node[fontname="{F}",fontsize=10]; edge[arrowhead=none,color="#555555"]; ranksep=1.1;']
    for a, lab in actors: s.append(f'{a}[shape=none,label="☺\\n{lab}",fontsize=11,image=""];')
    s.append(f'subgraph cluster_sys {{label="{system}";style=rounded;color="{G}";fontname="{F}";')
    for c, lab in cases: s.append(f'{c}[shape=ellipse,style=filled,fillcolor="#F2F6F3",color="{G}",label="{esc(lab)}"];')
    s.append('}')
    for l in links:
        if len(l) == 2: s.append(f'{l[0]}->{l[1]};')
        else: s.append(f'{l[0]}->{l[1]}[style=dashed,arrowhead=vee,label="«{l[2]}»",fontsize=8,fontname="{F}"];')
    s.append(extra + '}')
    render(name, '\n'.join(s))

ACT = {'ch': 'Chủ hộ', 'kt': 'Khách thuê', 'lt': 'Lễ tân', 'ke': 'Kế toán', 'ktt': 'Kế toán trưởng', 'dp': 'Điều phối viên', 'ktv': 'Kỹ thuật viên', 'ad': 'Admin', 'vn': '«system»\\nVNPay', 'gm': '«system»\\nGemini'}
uc('uc_tong_quat', [(k, v) for k, v in ACT.items()], 'Hệ thống quản lý chung cư Moons', [
    ('u0', 'Đăng nhập / OTP / Đổi mật khẩu'), ('u1', 'Quản lý căn hộ & nhân khẩu'), ('u2', 'Thẻ xe & thẻ ra vào'), ('u3', 'Chỉ số, hóa đơn & biểu phí'),
    ('u4', 'Thanh toán & đối soát'), ('u5', 'Đặt & quản lý tiện ích'), ('u6', 'Báo hỏng & xử lý sự cố'), ('u7', 'Trợ lý AI'), ('u8', 'Tài khoản & dữ liệu AI'), ('u9', 'Dashboard & báo cáo')],
    [('ch', 'u0'), ('ch', 'u1'), ('ch', 'u2'), ('ch', 'u4'), ('ch', 'u5'), ('ch', 'u6'), ('ch', 'u7'), ('kt', 'u0'), ('kt', 'u2'), ('kt', 'u4'), ('kt', 'u5'), ('kt', 'u6'), ('kt', 'u7'),
     ('u1', 'lt'), ('u2', 'lt'), ('u5', 'lt'), ('u3', 'ke'), ('u4', 'ke'), ('u9', 'ke'), ('u3', 'ktt'), ('u5', 'ktt'), ('u9', 'ktt'), ('u6', 'dp'), ('u6', 'ktv'), ('u8', 'ad'), ('u9', 'ad'), ('u5', 'ad'),
     ('u4', 'vn'), ('u7', 'gm')])
uc('uc_can_ho', [('ch', 'Chủ hộ'), ('kt', 'Khách thuê'), ('lt', 'Lễ tân')], 'Phân hệ căn hộ & nhân khẩu', [
    ('a', 'UC-RES01 Xem thông tin căn hộ'), ('b', 'UC-RES02 Khai báo thành viên'), ('c', 'Khai báo khách thuê'), ('d', 'UC-REC01 Tra cứu sơ đồ căn hộ'),
    ('e', 'UC-REC01 Duyệt / từ chối nhân khẩu'), ('f', 'Cấp tài khoản khách thuê'), ('g', 'Báo thành viên chuyển đi')],
    [('ch', 'a'), ('kt', 'a'), ('ch', 'b'), ('kt', 'b'), ('ch', 'c'), ('ch', 'g'), ('d', 'lt'), ('e', 'lt'), ('f', 'e', 'extend')])
uc('uc_tai_chinh', [('ch', 'Chủ hộ'), ('kt', 'Khách thuê'), ('ke', 'Kế toán'), ('ktt', 'Kế toán trưởng'), ('vn', '«system»\\nVNPay')], 'Phân hệ tài chính & thanh toán', [
    ('a', 'UC-RES05 Tra cứu hóa đơn'), ('b', 'UC-RES06 Thanh toán VNPay'), ('c', 'UC-ACC01 Nhập chỉ số'), ('d', 'UC-ACC02 Lập hóa đơn'), ('e', 'UC-KTT01 Phê duyệt phát hành'),
    ('f', 'UC-KTT01 Hủy hóa đơn'), ('g', 'UC-KTT02 Cập nhật biểu phí'), ('h', 'UC-ACC03 Gạch nợ thủ công'), ('i', 'UC-ACC04 Đối soát IPN'), ('j', 'UC-ACC05 Báo cáo công nợ'), ('k', 'Lọc theo hạn hợp đồng')],
    [('ch', 'a'), ('kt', 'a'), ('ch', 'b'), ('kt', 'b'), ('b', 'vn'), ('c', 'ke'), ('d', 'ke'), ('h', 'ke'), ('i', 'ke'), ('j', 'ke'), ('e', 'ktt'), ('f', 'ktt'), ('g', 'ktt'), ('k', 'a', 'extend'), ('d', 'c', 'include')])
uc('uc_the_xe_tien_ich', [('cd', 'Cư dân'), ('lt', 'Lễ tân'), ('ktt', 'Kế toán trưởng / Admin')], 'Phân hệ thẻ xe, thẻ từ & tiện ích', [
    ('a', 'UC-RES03 Xem thẻ & báo mất'), ('b', 'UC-RES04 Đăng ký thẻ xe'), ('c', 'UC-REC03 Duyệt thẻ xe'), ('d', 'UC-REC02 Cấp / khóa thẻ RFID, vân tay'),
    ('e', 'UC-RES07 Đặt tiện ích'), ('f', 'Hủy lịch'), ('g', 'UC-REC04 Xác thực vé QR'), ('h', 'UC-KTT03 Khóa lịch bảo trì'), ('i', 'Kiểm tra định mức xe')],
    [('cd', 'a'), ('cd', 'b'), ('cd', 'e'), ('cd', 'f'), ('c', 'lt'), ('d', 'lt'), ('g', 'lt'), ('h', 'ktt'), ('b', 'i', 'include'), ('c', 'i', 'include')])
uc('uc_su_co', [('cd', 'Cư dân'), ('dp', 'Điều phối viên'), ('ktv', 'Kỹ thuật viên'), ('gm', '«system»\\nGemini'), ('ad', 'Admin')], 'Phân hệ sự cố & trợ lý AI', [
    ('a', 'UC-RES08 Gửi phiếu báo hỏng'), ('b', 'Theo dõi tiến độ'), ('c', 'Nghiệm thu / yêu cầu sửa lại'), ('d', 'UC-DPV01 Phân công / từ chối'),
    ('e', 'UC-TEC01 Cập nhật Kanban'), ('f', 'UC-TEC02 Gửi nghiệm thu kèm ảnh'), ('g', 'UC-RES09 Hỏi đáp Trợ lý AI'), ('h', 'UC-ADM02 Quản lý ngữ cảnh AI')],
    [('cd', 'a'), ('cd', 'b'), ('cd', 'c'), ('cd', 'g'), ('d', 'dp'), ('e', 'ktv'), ('f', 'ktv'), ('g', 'gm'), ('h', 'ad')])

# ---------------- DFD mức 1 ----------------
render('dfd_muc_1', f'''digraph G {{ rankdir=TB; fontname="{F}"; node[fontname="{F}",fontsize=10]; edge[fontname="{F}",fontsize=8,color="#555555",arrowsize=0.6]; ranksep=0.7; nodesep=0.35;
node[shape=box,style=filled,fillcolor="#E2E8F0",color="#4A5568"]; CD[label="Cư dân"]; LT[label="Lễ tân"]; KE[label="Kế toán"]; KTT[label="Kế toán trưởng"]; DPV[label="Điều phối viên"]; KTV[label="Kỹ thuật viên"]; AD[label="Admin"]; VN[label="VNPay"];
node[shape=circle,style=filled,fillcolor="#F2F6F3",color="{G}",fixedsize=true,width=1.35];
P1[label="1.0\\nQuản lý\\nnhân khẩu"]; P2[label="2.0\\nThẻ xe &\\nthẻ ra vào"]; P3[label="3.0\\nChỉ số &\\nhóa đơn"]; P4[label="4.0\\nThanh toán &\\nđối soát"]; P5[label="5.0\\nĐặt\\ntiện ích"]; P6[label="6.0\\nXử lý\\nsự cố"]; P7[label="7.0\\nTrợ lý AI"];
node[shape=record,style=filled,fillcolor="#FFF7E6",color="#B7791F",fixedsize=false,width=0];
D1[label="D1|nhan_khau, can_ho"]; D2[label="D2|the_xe, the_ra_vao"]; D3[label="D3|chi_so_dien_nuoc, bieu_phi"]; D4[label="D4|hoa_don, chi_tiet_hoa_don"]; D5[label="D5|giao_dich_thanh_toan"]; D6[label="D6|dich_vu_tien_ich, dat_tien_ich"]; D7[label="D7|phieu_su_co"]; D8[label="D8|ngu_canh_ai"];
CD->P1[label="khai báo"]; P1->LT[label="hồ sơ chờ duyệt"]; LT->P1[label="kết quả duyệt"]; P1->D1;
CD->P2[label="đăng ký xe"]; LT->P2[label="duyệt, mã RFID"]; P2->D2; D1->P2;
KE->P3[label="chỉ số"]; KTT->P3[label="phê duyệt/hủy"]; D3->P3; D2->P3[label="xe đã duyệt"]; P3->D4; P3->D3[label="khóa sổ"];
CD->P4[label="yêu cầu TT"]; P4->VN[label="URL ký"]; VN->P4[label="IPN"]; KE->P4[label="phiếu thu"]; D4->P4; P4->D5; P4->D4[label="gạch nợ"];
CD->P5[label="đặt lịch"]; D4->P5[label="nợ quá hạn"]; P5->D6; LT->P5[label="mã vé QR"]; KTT->P5[label="khóa bảo trì"]; AD->P5[label="khóa bảo trì"];
CD->P6[label="phiếu + ảnh"]; DPV->P6[label="phân công"]; KTV->P6[label="tiến độ, ảnh"]; P6->D7; P6->CD[label="tiến độ"];
CD->P7[label="câu hỏi"]; D8->P7[label="đoạn liên quan"]; D4->P7[label="HĐ của chính cư dân"]; AD->P7[label="tài liệu"]; P7->CD[label="trả lời"];
}}''')

# ---------------- ERD (sinh tự động từ CSDL) ----------------
sch = json.load(open('/home/claude/schema.json'))
s = [f'digraph G {{ rankdir=LR; fontname="{F}"; node[shape=plaintext,fontname="{F}",fontsize=9]; edge[fontname="{F}",fontsize=8,color="#666666",arrowhead=crow,arrowtail=tee,dir=both]; ranksep=0.9; nodesep=0.3;']
for t, d in sch.items():
    if t.startswith('_'): continue
    fks = {f['c'] for f in d['fk']}
    rows = ''
    for c in d['cols']:
        tag = 'PK' if c['k'] == 'PRI' else ('FK' if c['c'] in fks else '')
        if not tag and c['c'] not in ('ma_can_ho', 'ma_hoa_don', 'ky_cuoc', 'trang_thai_thanh_toan', 'tong_tien', 'bien_so_xe', 'loai_xe', 'trang_thai_duyet', 'vai_tro',
                                      'ten_dang_nhap', 'loai_cu_dan', 'ngay_dat', 'khung_gio_bat_dau', 'trang_thai_kanban', 'so_tien', 'trang_thai', 'thanh_tien', 'ma_phi', 'don_gia',
                                      'ten_tien_ich', 'doc_quyen', 'tieu_de', 'hanh_dong', 'tieu_de_tai_lieu', 'loai_the', 'chi_so_dien_moi', 'ma_ve', 'hd_thanh_cong', 'ky_hieu_luc', 'bien_so_hieu_luc'):
            continue
        col = '#7B341E' if tag == 'PK' else ('#2B6CB0' if tag == 'FK' else '#333')
        gen = ' (sinh)' if c['g'] else ''
        rows += f'<tr><td align="left"><font color="{col}">{("<b>"+tag+"</b>") if tag else " "}</font></td><td align="left"><font color="{col}">{c["c"]}{gen}</font></td></tr>'
    s.append(f'{t}[label=<<table border="1" cellborder="0" cellspacing="0" cellpadding="3" color="{G}"><tr><td colspan="2" bgcolor="{G}"><font color="white"><b>{t}</b></font></td></tr>{rows}<tr><td colspan="2"><font point-size="7" color="#888888">… {len(d["cols"])} cột</font></td></tr></table>>];')
for t, d in sch.items():
    if t.startswith('_'): continue
    for f in d['fk']:
        if f['rt'] == 'tai_khoan' and t not in ('nhan_khau',) and f['c'] != 'ID_ky_thuat_vien': continue  # giảm rối: bỏ các FK "người thực hiện"
        lab = '1 – 0..1' if (t == 'nhan_khau' and f['c'] == 'ID_tai_khoan') else '1 – N'
        s.append(f'{f["rt"]}->{t}[label="{lab}\\n{f["c"]}"' + (',arrowhead=teeodot' if lab.startswith('1 – 0') else '') + '];')
s.append('}')
render('erd', '\n'.join(s))
print('done', sorted(os.listdir(OUT)))
