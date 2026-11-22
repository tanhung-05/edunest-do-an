<?php

/*
 * Nội dung thật cho các khóa khối Khoa học tự nhiên (bám chương trình GDPT 2018).
 * Mỗi khóa: tóm tắt, mục tiêu, mô tả, 2 chương x 3 bài đọc, 5 câu hỏi (đáp án đúng theo chỉ số).
 */

return [
    'Hóa 10 cơ bản' => [
        'summary' => 'Nắm vững cấu tạo nguyên tử, bảng tuần hoàn và liên kết hóa học – nền móng cho toàn bộ Hóa học THPT.',
        'objectives' => "Mô tả được cấu tạo nguyên tử và cấu hình electron của 20 nguyên tố đầu.\nGiải thích quy luật biến đổi tính chất trong bảng tuần hoàn.\nPhân biệt liên kết ion, cộng hóa trị và viết được công thức Lewis.",
        'description' => "Khóa học dành cho học sinh lớp 10 mới bắt đầu học Hóa theo chương trình mới. Mỗi bài gồm phần lý thuyết ngắn gọn, ví dụ minh họa và câu hỏi tự kiểm tra.\n\nSau khóa học, em có thể tự đọc bảng tuần hoàn, dự đoán tính chất của nguyên tố và loại liên kết trong hợp chất.",
        'chapters' => [
            ['Chương 1. Cấu tạo nguyên tử', [
                ['Thành phần của nguyên tử', '<p>Nguyên tử gồm <strong>hạt nhân</strong> mang điện dương ở tâm và <strong>lớp vỏ electron</strong> mang điện âm chuyển động xung quanh. Hạt nhân chứa proton (điện tích +1) và neutron (không mang điện).</p><p>Khối lượng nguyên tử tập trung gần như hoàn toàn ở hạt nhân vì khối lượng electron chỉ bằng khoảng 1/1840 khối lượng proton. Nguyên tử trung hòa về điện nên số proton bằng số electron.</p><h3>Ví dụ</h3><p>Nguyên tử natri có 11 proton, 12 neutron và 11 electron, nên số khối A = 11 + 12 = 23.</p>'],
                ['Nguyên tố hóa học và đồng vị', '<p><strong>Nguyên tố hóa học</strong> là tập hợp các nguyên tử có cùng số proton (cùng điện tích hạt nhân Z). Kí hiệu nguyên tử ghi số khối A ở trên, số hiệu Z ở dưới bên trái kí hiệu nguyên tố.</p><p><strong>Đồng vị</strong> là các nguyên tử của cùng một nguyên tố nhưng khác số neutron, ví dụ <sup>35</sup>Cl và <sup>37</sup>Cl. Nguyên tử khối trung bình được tính theo tỉ lệ phần trăm số nguyên tử của từng đồng vị.</p><p>Với clo: 35 × 75,77% + 37 × 24,23% ≈ 35,5.</p>'],
                ['Cấu hình electron', '<p>Electron được sắp xếp vào các lớp (n = 1, 2, 3…) và phân lớp s, p, d, f theo thứ tự mức năng lượng tăng dần: 1s 2s 2p 3s 3p 4s 3d…</p><ul><li>Phân lớp s chứa tối đa 2 electron, p tối đa 6, d tối đa 10.</li><li>Lớp ngoài cùng có 1–3 electron thường là kim loại, 5–7 electron thường là phi kim, 8 electron là khí hiếm.</li></ul><p>Ví dụ: Mg (Z = 12): 1s<sup>2</sup>2s<sup>2</sup>2p<sup>6</sup>3s<sup>2</sup>, có 2 electron lớp ngoài cùng nên là kim loại.</p>'],
            ]],
            ['Chương 2. Bảng tuần hoàn và liên kết hóa học', [
                ['Cấu tạo bảng tuần hoàn', '<p>Bảng tuần hoàn xếp các nguyên tố theo chiều tăng dần điện tích hạt nhân. Các nguyên tố có cùng số lớp electron nằm trong cùng một <strong>chu kì</strong>; các nguyên tố có số electron hóa trị giống nhau nằm trong cùng một <strong>nhóm</strong>.</p><p>Số thứ tự ô = số hiệu nguyên tử Z; số thứ tự chu kì = số lớp electron; với nhóm A, số thứ tự nhóm = số electron lớp ngoài cùng.</p>'],
                ['Xu hướng biến đổi tính chất', '<p>Trong một chu kì, theo chiều tăng Z: bán kính nguyên tử giảm, độ âm điện tăng, tính kim loại giảm và tính phi kim tăng.</p><p>Trong một nhóm A, theo chiều tăng Z: bán kính tăng, độ âm điện giảm, tính kim loại tăng.</p><blockquote>Mẹo nhớ: góc dưới bên trái bảng là kim loại mạnh nhất (Cs, Fr), góc trên bên phải (trừ khí hiếm) là phi kim mạnh nhất (F).</blockquote>'],
                ['Liên kết ion và liên kết cộng hóa trị', '<p><strong>Liên kết ion</strong> hình thành do lực hút tĩnh điện giữa ion dương và ion âm, thường giữa kim loại điển hình và phi kim điển hình (NaCl, MgO).</p><p><strong>Liên kết cộng hóa trị</strong> hình thành bằng các cặp electron dùng chung giữa hai nguyên tử phi kim. Nếu cặp electron lệch về nguyên tử có độ âm điện lớn hơn thì liên kết phân cực (HCl, H<sub>2</sub>O); nếu không lệch thì không phân cực (H<sub>2</sub>, Cl<sub>2</sub>).</p><p>Hiệu độ âm điện Δχ ≥ 1,7 thường là liên kết ion.</p>'],
            ]],
        ],
        'questions' => [
            ['Hạt nào mang điện tích dương trong nguyên tử?', ['Electron', 'Proton', 'Neutron', 'Hạt nhân và electron'], 1],
            ['Nguyên tử có Z = 12, số khối 24 có bao nhiêu neutron?', ['10', '12', '24', '36'], 1],
            ['Trong một chu kì, theo chiều tăng điện tích hạt nhân, độ âm điện thường', ['Giảm dần', 'Tăng dần', 'Không đổi', 'Tăng rồi giảm'], 1],
            ['Liên kết trong phân tử NaCl là', ['Cộng hóa trị không phân cực', 'Cộng hóa trị phân cực', 'Liên kết ion', 'Liên kết kim loại'], 2],
            ['Cấu hình 1s²2s²2p⁶3s¹ là của nguyên tố', ['Mg', 'Na', 'Al', 'Ne'], 1],
        ],
    ],

    'Hóa 11 vô cơ' => [
        'summary' => 'Cân bằng hóa học, nitrogen và sulfur – những chuyên đề vô cơ trọng tâm của lớp 11, có bài tập vận dụng theo đề thi.',
        'objectives' => "Viết được biểu thức hằng số cân bằng và vận dụng nguyên lí Le Chatelier.\nTính pH của dung dịch acid, base mạnh.\nTrình bày tính chất của nitrogen, ammonia, sulfur và sulfuric acid.",
        'description' => 'Khóa học đi qua ba chuyên đề vô cơ lớp 11 theo chương trình mới. Lý thuyết được tóm gọn thành sơ đồ, kèm ví dụ thực tế như sản xuất phân đạm và mưa acid.',
        'chapters' => [
            ['Chương 1. Cân bằng hóa học', [
                ['Phản ứng thuận nghịch và hằng số cân bằng', '<p>Phản ứng thuận nghịch xảy ra đồng thời theo hai chiều ngược nhau. Khi tốc độ phản ứng thuận bằng tốc độ phản ứng nghịch, hệ đạt <strong>trạng thái cân bằng</strong>.</p><p>Với phản ứng aA + bB ⇌ cC + dD, hằng số cân bằng K<sub>C</sub> = [C]<sup>c</sup>[D]<sup>d</sup> / ([A]<sup>a</sup>[B]<sup>b</sup>). Chất rắn không có mặt trong biểu thức K<sub>C</sub>.</p>'],
                ['Nguyên lí chuyển dịch cân bằng Le Chatelier', '<p>Khi một hệ đang cân bằng chịu tác động từ bên ngoài (nồng độ, áp suất, nhiệt độ), cân bằng sẽ chuyển dịch theo chiều <strong>làm giảm</strong> tác động đó.</p><ul><li>Tăng nồng độ chất phản ứng: cân bằng chuyển theo chiều thuận.</li><li>Tăng áp suất: chuyển về phía có ít phân tử khí hơn.</li><li>Tăng nhiệt độ: chuyển theo chiều thu nhiệt.</li></ul><p>Chất xúc tác không làm chuyển dịch cân bằng, chỉ giúp đạt cân bằng nhanh hơn.</p>'],
                ['pH và chất chỉ thị', '<p>pH = −lg[H<sup>+</sup>]. Dung dịch có pH &lt; 7 là môi trường acid, pH = 7 trung tính, pH &gt; 7 môi trường base.</p><p>Ví dụ: dung dịch HCl 0,01 M có [H<sup>+</sup>] = 10<sup>−2</sup> M nên pH = 2. Dung dịch NaOH 0,001 M có pOH = 3 nên pH = 11.</p><p>Quỳ tím hóa đỏ trong acid, hóa xanh trong base; phenolphthalein không màu trong acid, hồng trong base.</p>'],
            ]],
            ['Chương 2. Nitrogen và sulfur', [
                ['Nitrogen và ammonia', '<p>Nitrogen chiếm khoảng 78% thể tích không khí, phân tử N<sub>2</sub> có liên kết ba bền nên khá trơ ở nhiệt độ thường.</p><p>Ammonia được tổng hợp theo quá trình Haber: N<sub>2</sub> + 3H<sub>2</sub> ⇌ 2NH<sub>3</sub> (xúc tác Fe, 400–450 °C, 150–200 atm). NH<sub>3</sub> tan nhiều trong nước, có tính base yếu và là nguyên liệu sản xuất phân đạm, nitric acid.</p>'],
                ['Sulfur và sulfur dioxide', '<p>Sulfur vừa có tính oxi hóa (tác dụng với kim loại, hydrogen) vừa có tính khử (tác dụng với oxygen). SO<sub>2</sub> là khí không màu, mùi hắc, sinh ra khi đốt nhiên liệu chứa sulfur.</p><p>SO<sub>2</sub> trong khí quyển bị oxi hóa và hòa tan trong nước mưa tạo H<sub>2</sub>SO<sub>4</sub>, là nguyên nhân chính gây <strong>mưa acid</strong>, ăn mòn công trình và làm chua đất.</p>'],
                ['Sulfuric acid', '<p>H<sub>2</sub>SO<sub>4</sub> loãng có đầy đủ tính chất của một acid mạnh. H<sub>2</sub>SO<sub>4</sub> đặc có tính oxi hóa mạnh và tính háo nước: làm than hóa đường, giấy, vải.</p><p>Quy tắc an toàn: khi pha loãng phải <strong>rót từ từ acid vào nước</strong> và khuấy đều, tuyệt đối không làm ngược lại.</p><p>Nhận biết ion sulfate bằng dung dịch BaCl<sub>2</sub>: tạo kết tủa trắng BaSO<sub>4</sub> không tan trong acid.</p>'],
            ]],
        ],
        'questions' => [
            ['Yếu tố nào KHÔNG làm chuyển dịch cân bằng hóa học?', ['Nồng độ', 'Nhiệt độ', 'Áp suất', 'Chất xúc tác'], 3],
            ['Dung dịch HCl 0,01 M có pH bằng', ['1', '2', '12', '13'], 1],
            ['Khí gây ra hiện tượng mưa acid chủ yếu là', ['CO₂', 'SO₂', 'N₂', 'O₂'], 1],
            ['Thuốc thử nhận biết ion sulfate là', ['AgNO₃', 'BaCl₂', 'NaOH', 'Quỳ tím'], 1],
            ['Khi pha loãng H₂SO₄ đặc cần', ['Rót nước vào acid', 'Rót từ từ acid vào nước', 'Đun nóng hỗn hợp', 'Rót nhanh acid vào nước'], 1],
        ],
    ],

    'Luyện đề Hóa THPT' => [
        'summary' => 'Hệ thống dạng bài và chiến thuật làm đề Hóa thi tốt nghiệp THPT, luyện tốc độ với đề minh họa.',
        'objectives' => "Nhận diện nhanh 10 dạng câu hỏi thường gặp trong đề thi.\nVận dụng bảo toàn khối lượng, bảo toàn electron để giải nhanh.\nPhân bổ thời gian hợp lý cho 40 câu trong 50 phút.",
        'description' => 'Khóa luyện đề dành cho học sinh lớp 12 đã học xong chương trình. Mỗi chương tổng hợp phương pháp giải và bài tập mẫu có lời giải chi tiết.',
        'chapters' => [
            ['Chương 1. Phương pháp giải nhanh', [
                ['Bảo toàn khối lượng', '<p>Trong phản ứng hóa học, tổng khối lượng các chất tham gia bằng tổng khối lượng các chất sản phẩm.</p><p><strong>Ví dụ:</strong> Cho 10 g hỗn hợp kim loại tác dụng hết với dung dịch HCl thu được 0,2 mol H<sub>2</sub>. Khối lượng muối = 10 + 0,4 × 35,5 = 24,2 g (vì n<sub>Cl⁻</sub> = 2n<sub>H₂</sub>).</p>'],
                ['Bảo toàn electron', '<p>Trong phản ứng oxi hóa – khử, tổng số mol electron chất khử nhường bằng tổng số mol electron chất oxi hóa nhận.</p><p><strong>Ví dụ:</strong> 5,6 g Fe tan hết trong HNO<sub>3</sub> dư tạo NO duy nhất. n<sub>Fe</sub> = 0,1 mol nhường 0,3 mol e, nên n<sub>NO</sub> = 0,3 / 3 = 0,1 mol, V = 2,479 L (đkc).</p>'],
                ['Phân bổ thời gian làm bài', '<p>Đề thi gồm 40 câu trong 50 phút, trung bình 75 giây mỗi câu. Chiến thuật hiệu quả:</p><ol><li>Lượt 1 (25 phút): làm 25 câu lý thuyết và bài tập cơ bản.</li><li>Lượt 2 (20 phút): làm bài tập vận dụng.</li><li>5 phút cuối: tô đủ đáp án, kiểm tra câu đánh dấu.</li></ol>'],
            ]],
            ['Chương 2. Dạng bài trọng tâm', [
                ['Bài toán este – chất béo', '<p>Thủy phân chất béo trong NaOH: (RCOO)<sub>3</sub>C<sub>3</sub>H<sub>5</sub> + 3NaOH → 3RCOONa + C<sub>3</sub>H<sub>5</sub>(OH)<sub>3</sub>.</p><p>Luôn có n<sub>NaOH</sub> = 3n<sub>chất béo</sub> = 3n<sub>glycerol</sub>. Kết hợp bảo toàn khối lượng: m<sub>chất béo</sub> + m<sub>NaOH</sub> = m<sub>xà phòng</sub> + m<sub>glycerol</sub>.</p>'],
                ['Bài toán kim loại tác dụng với acid', '<p>Kim loại đứng trước H tác dụng với HCl, H<sub>2</sub>SO<sub>4</sub> loãng giải phóng H<sub>2</sub>. Công thức nhanh: m<sub>muối clorua</sub> = m<sub>kim loại</sub> + 71n<sub>H₂</sub>; m<sub>muối sulfate</sub> = m<sub>kim loại</sub> + 96n<sub>H₂</sub>.</p>'],
                ['Bài toán đồ thị CO₂ tác dụng với dung dịch kiềm', '<p>Sục CO<sub>2</sub> vào Ca(OH)<sub>2</sub>: ban đầu kết tủa CaCO<sub>3</sub> tăng dần đến cực đại, sau đó tan dần do tạo Ca(HCO<sub>3</sub>)<sub>2</sub>.</p><p>Đồ thị có dạng tam giác cân: cùng một lượng kết tủa ứng với hai giá trị n<sub>CO₂</sub> đối xứng qua đỉnh.</p>'],
            ]],
        ],
        'questions' => [
            ['Cho 10 g kim loại tan hết trong HCl thu 0,2 mol H₂. Khối lượng muối là', ['17,1 g', '24,2 g', '20,0 g', '31,3 g'], 1],
            ['Thủy phân 1 mol chất béo cần bao nhiêu mol NaOH?', ['1', '2', '3', '4'], 2],
            ['5,6 g Fe tan hết trong HNO₃ dư tạo NO duy nhất. Số mol NO là', ['0,05', '0,1', '0,15', '0,3'], 1],
            ['Thời gian trung bình cho mỗi câu trong đề 40 câu 50 phút là', ['60 giây', '75 giây', '90 giây', '120 giây'], 1],
            ['Sản phẩm phụ khi xà phòng hóa chất béo là', ['Ethanol', 'Glycerol', 'Methanol', 'Acetic acid'], 1],
        ],
    ],

    'Vật lý 10 cơ học' => [
        'summary' => 'Động học, động lực học và các định luật bảo toàn – nền tảng cơ học lớp 10 trình bày dễ hiểu, nhiều ví dụ đời sống.',
        'objectives' => "Phân biệt được độ dịch chuyển, quãng đường, vận tốc và tốc độ.\nVận dụng ba định luật Newton giải bài toán chuyển động.\nÁp dụng định luật bảo toàn cơ năng và động lượng.",
        'description' => 'Khóa học bám sát sách Vật lí 10 chương trình mới. Mỗi bài có tóm tắt công thức, ví dụ minh họa từ đời sống như xe buýt, thang máy, ném bóng.',
        'chapters' => [
            ['Chương 1. Động học', [
                ['Độ dịch chuyển và quãng đường', '<p><strong>Quãng đường</strong> là độ dài đường đi, luôn dương. <strong>Độ dịch chuyển</strong> là vectơ nối vị trí đầu và vị trí cuối, có thể bằng 0 nếu vật quay về chỗ cũ.</p><p>Ví dụ: em đi bộ từ nhà đến trường 1,2 km rồi quay về, quãng đường là 2,4 km nhưng độ dịch chuyển bằng 0.</p>'],
                ['Chuyển động thẳng biến đổi đều', '<p>Gia tốc a = Δv / Δt không đổi. Các công thức:</p><ul><li>v = v<sub>0</sub> + at</li><li>s = v<sub>0</sub>t + ½at<sup>2</sup></li><li>v<sup>2</sup> − v<sub>0</sub><sup>2</sup> = 2as</li></ul><p>Xe đang chạy 10 m/s hãm phanh với a = −2 m/s<sup>2</sup> sẽ dừng sau 5 s và đi thêm 25 m.</p>'],
                ['Rơi tự do', '<p>Rơi tự do là chuyển động chỉ dưới tác dụng của trọng lực, gia tốc g ≈ 9,8 m/s<sup>2</sup> và không phụ thuộc khối lượng vật.</p><p>Quãng đường rơi h = ½gt<sup>2</sup>. Một viên bi rơi từ độ cao 20 m (lấy g = 10 m/s<sup>2</sup>) chạm đất sau 2 s với vận tốc 20 m/s.</p>'],
            ]],
            ['Chương 2. Động lực học và bảo toàn', [
                ['Ba định luật Newton', '<p><strong>Định luật I:</strong> vật giữ nguyên trạng thái đứng yên hoặc chuyển động thẳng đều khi hợp lực bằng 0 (quán tính).</p><p><strong>Định luật II:</strong> a = F / m.</p><p><strong>Định luật III:</strong> lực và phản lực cùng độ lớn, ngược chiều, đặt vào hai vật khác nhau. Khi em đẩy tường, tường cũng đẩy lại em.</p>'],
                ['Động năng, thế năng và cơ năng', '<p>Động năng W<sub>đ</sub> = ½mv<sup>2</sup>; thế năng trọng trường W<sub>t</sub> = mgh. Khi chỉ chịu tác dụng của trọng lực, cơ năng W = W<sub>đ</sub> + W<sub>t</sub> được bảo toàn.</p><p>Vật rơi từ độ cao h không vận tốc đầu chạm đất với v = √(2gh).</p>'],
                ['Động lượng và va chạm', '<p>Động lượng p = mv là đại lượng vectơ. Trong hệ kín, tổng động lượng được bảo toàn.</p><p>Hiện tượng súng giật lùi khi bắn hay tên lửa phóng lên đều giải thích bằng định luật bảo toàn động lượng.</p>'],
            ]],
        ],
        'questions' => [
            ['Đi từ nhà đến trường 1,2 km rồi quay về nhà. Độ dịch chuyển là', ['0 km', '1,2 km', '2,4 km', '0,6 km'], 0],
            ['Vật rơi tự do từ độ cao 20 m (g = 10 m/s²) chạm đất sau', ['1 s', '2 s', '4 s', '20 s'], 1],
            ['Biểu thức định luật II Newton là', ['F = ma', 'F = mv', 'p = mv', 'W = mgh'], 0],
            ['Động năng của vật khối lượng 2 kg chuyển động 3 m/s là', ['3 J', '6 J', '9 J', '18 J'], 2],
            ['Súng giật lùi khi bắn được giải thích bằng', ['Định luật I Newton', 'Bảo toàn động lượng', 'Bảo toàn cơ năng', 'Định luật Hooke'], 1],
        ],
    ],

    'Vật lý 12 điện xoay chiều' => [
        'summary' => 'Chinh phục dòng điện xoay chiều: mạch RLC, cộng hưởng, công suất và máy biến áp – chuyên đề chiếm nhiều điểm trong đề thi.',
        'objectives' => "Viết được biểu thức u, i và độ lệch pha trong mạch RLC.\nTính tổng trở, công suất, hệ số công suất.\nGiải bài toán cộng hưởng và truyền tải điện năng.",
        'description' => 'Chuyên đề điện xoay chiều được chia nhỏ thành từng dạng bài, có giản đồ vectơ minh họa và mẹo bấm máy tính.',
        'chapters' => [
            ['Chương 1. Mạch điện xoay chiều', [
                ['Đại cương dòng điện xoay chiều', '<p>Dòng điện xoay chiều có cường độ biến thiên điều hòa: i = I<sub>0</sub>cos(ωt + φ). Giá trị hiệu dụng I = I<sub>0</sub>/√2.</p><p>Điện lưới Việt Nam có điện áp hiệu dụng 220 V, tần số 50 Hz, tức ω = 100π rad/s.</p>'],
                ['Mạch RLC nối tiếp', '<p>Cảm kháng Z<sub>L</sub> = ωL, dung kháng Z<sub>C</sub> = 1/(ωC). Tổng trở Z = √(R<sup>2</sup> + (Z<sub>L</sub> − Z<sub>C</sub>)<sup>2</sup>).</p><p>Độ lệch pha: tan φ = (Z<sub>L</sub> − Z<sub>C</sub>)/R. Nếu Z<sub>L</sub> &gt; Z<sub>C</sub> mạch có tính cảm kháng, u sớm pha hơn i.</p>'],
                ['Hiện tượng cộng hưởng điện', '<p>Khi Z<sub>L</sub> = Z<sub>C</sub> hay ω<sup>2</sup>LC = 1, mạch xảy ra cộng hưởng: tổng trở nhỏ nhất Z = R, cường độ dòng điện cực đại, u cùng pha với i và hệ số công suất bằng 1.</p>'],
            ]],
            ['Chương 2. Công suất và truyền tải điện năng', [
                ['Công suất và hệ số công suất', '<p>Công suất tiêu thụ P = UIcos φ = I<sup>2</sup>R. Hệ số công suất cos φ = R/Z.</p><p>Các nhà máy yêu cầu cos φ ≥ 0,85 để giảm hao phí trên đường dây.</p>'],
                ['Máy biến áp', '<p>Máy biến áp hoạt động dựa trên hiện tượng cảm ứng điện từ, chỉ biến đổi điện áp xoay chiều. Với máy lí tưởng: U<sub>1</sub>/U<sub>2</sub> = N<sub>1</sub>/N<sub>2</sub> = I<sub>2</sub>/I<sub>1</sub>.</p>'],
                ['Truyền tải điện năng đi xa', '<p>Công suất hao phí trên đường dây ΔP = P<sup>2</sup>R/(U<sup>2</sup>cos<sup>2</sup>φ). Tăng điện áp truyền tải lên n lần thì hao phí giảm n<sup>2</sup> lần, vì vậy đường dây 500 kV được dùng để truyền điện Bắc – Nam.</p>'],
            ]],
        ],
        'questions' => [
            ['Điện lưới Việt Nam có tần số', ['50 Hz', '60 Hz', '100 Hz', '220 Hz'], 0],
            ['Khi mạch RLC xảy ra cộng hưởng thì', ['Z lớn nhất', 'Z = R', 'I nhỏ nhất', 'cos φ = 0'], 1],
            ['Tăng điện áp truyền tải lên 10 lần thì hao phí giảm', ['10 lần', '20 lần', '100 lần', '1000 lần'], 2],
            ['Máy biến áp KHÔNG biến đổi được', ['Điện áp xoay chiều', 'Cường độ xoay chiều', 'Điện áp một chiều', 'Điện áp hiệu dụng'], 2],
            ['Cảm kháng của cuộn dây được tính bằng', ['ωL', '1/(ωL)', 'ωC', 'R + ωL'], 0],
        ],
    ],

    'Luyện đề Vật lý THPT' => [
        'summary' => 'Tổng ôn và luyện đề Vật lí tốt nghiệp THPT theo cấu trúc mới, tập trung các câu phân loại 8–9 điểm.',
        'objectives' => "Ôn lại công thức cốt lõi của 4 chủ đề lớp 12.\nLuyện tốc độ với đề 40 câu.\nTránh các lỗi sai thường gặp về đơn vị và dấu.",
        'description' => 'Khóa học gồm phần tổng ôn công thức và phần chữa đề minh họa, phù hợp học sinh muốn đạt 8+ môn Vật lí.',
        'chapters' => [
            ['Chương 1. Tổng ôn công thức', [
                ['Dao động cơ', '<p>Phương trình x = Acos(ωt + φ). Chu kì con lắc lò xo T = 2π√(m/k); con lắc đơn T = 2π√(l/g). Cơ năng W = ½kA<sup>2</sup> không đổi khi bỏ qua ma sát.</p>'],
                ['Sóng cơ và sóng âm', '<p>Bước sóng λ = v/f. Hai điểm trên phương truyền cách nhau d dao động lệch pha Δφ = 2πd/λ. Âm nghe được có tần số 16 Hz đến 20 000 Hz.</p>'],
                ['Vật lí hạt nhân', '<p>Định luật phóng xạ N = N<sub>0</sub>·2<sup>−t/T</sup>. Sau 3 chu kì bán rã, lượng chất phóng xạ còn lại 1/8 ban đầu. Năng lượng liên kết riêng càng lớn hạt nhân càng bền.</p>'],
            ]],
            ['Chương 2. Chữa đề và lỗi thường gặp', [
                ['Lỗi đổi đơn vị', '<p>Nhiều thí sinh mất điểm do quên đổi cm sang m, g sang kg, hoặc dùng độ thay vì radian khi bấm máy. Hãy viết đơn vị ngay cạnh mỗi số liệu trước khi thay vào công thức.</p>'],
                ['Kĩ thuật loại trừ đáp án', '<p>Với câu trắc nghiệm, kiểm tra thứ nguyên và trường hợp đặc biệt (cho tham số bằng 0 hoặc rất lớn) để loại nhanh đáp án sai trước khi tính toán.</p>'],
                ['Chữa đề minh họa', '<p>Phân tích đề minh họa theo ba mức: 28 câu nhận biết – thông hiểu, 8 câu vận dụng, 4 câu vận dụng cao. Mục tiêu 8 điểm cần làm chắc 32 câu đầu.</p>'],
            ]],
        ],
        'questions' => [
            ['Chu kì con lắc lò xo phụ thuộc vào', ['Biên độ', 'Khối lượng và độ cứng', 'Gia tốc trọng trường', 'Chiều dài dây'], 1],
            ['Sau 3 chu kì bán rã, lượng chất phóng xạ còn lại', ['1/3', '1/6', '1/8', '1/9'], 2],
            ['Âm nghe được có tần số trong khoảng', ['0–16 Hz', '16–20 000 Hz', '20 000–40 000 Hz', 'Trên 20 000 Hz'], 1],
            ['Bước sóng được tính bằng', ['v·f', 'v/f', 'f/v', 'v·T²'], 1],
            ['Hạt nhân càng bền khi', ['Số khối càng lớn', 'Năng lượng liên kết riêng càng lớn', 'Số proton càng nhỏ', 'Khối lượng càng lớn'], 1],
        ],
    ],

    'Sinh học 12 di truyền' => [
        'summary' => 'Cơ chế di truyền và biến dị, quy luật Mendel – chuyên đề Sinh học 12 trình bày bằng sơ đồ trực quan.',
        'objectives' => "Mô tả quá trình nhân đôi DNA, phiên mã và dịch mã.\nPhân biệt các dạng đột biến gene và đột biến nhiễm sắc thể.\nGiải bài tập lai một và hai cặp tính trạng.",
        'description' => 'Khóa học giúp học sinh lớp 12 nắm chắc phần Di truyền học – phần chiếm tỉ trọng lớn nhất trong đề thi Sinh học.',
        'chapters' => [
            ['Chương 1. Cơ chế di truyền', [
                ['Gene và mã di truyền', '<p>Gene là một đoạn DNA mang thông tin mã hóa một sản phẩm xác định. Mã di truyền là mã bộ ba: cứ 3 nucleotide liên tiếp quy định một amino acid.</p><p>Có 64 bộ ba, trong đó AUG là mã mở đầu và UAA, UAG, UGA là mã kết thúc.</p>'],
                ['Nhân đôi DNA', '<p>DNA nhân đôi theo nguyên tắc <strong>bổ sung</strong> (A–T, G–C) và <strong>bán bảo toàn</strong>: mỗi DNA con có một mạch cũ và một mạch mới. Enzyme DNA polymerase chỉ tổng hợp mạch mới theo chiều 5′→3′.</p>'],
                ['Phiên mã và dịch mã', '<p>Phiên mã tạo mRNA từ mạch khuôn của gene; dịch mã ở ribosome tổng hợp chuỗi polypeptide dựa trên trình tự codon của mRNA, tRNA mang amino acid tới theo nguyên tắc bổ sung codon – anticodon.</p>'],
            ]],
            ['Chương 2. Quy luật di truyền', [
                ['Quy luật phân li của Mendel', '<p>Mỗi tính trạng do một cặp allele quy định; khi giảm phân, các allele phân li đồng đều về các giao tử. Lai Aa × Aa cho tỉ lệ kiểu gene 1AA : 2Aa : 1aa và kiểu hình 3 trội : 1 lặn.</p>'],
                ['Quy luật phân li độc lập', '<p>Các cặp allele nằm trên các cặp nhiễm sắc thể khác nhau phân li độc lập. Lai AaBb × AaBb cho tỉ lệ kiểu hình 9 : 3 : 3 : 1.</p>'],
                ['Đột biến gene', '<p>Đột biến gene gồm thay thế, thêm hoặc mất một cặp nucleotide. Đột biến thêm hoặc mất một cặp làm dịch khung đọc, thường gây hậu quả nặng hơn đột biến thay thế.</p>'],
            ]],
        ],
        'questions' => [
            ['Bộ ba mở đầu trên mRNA là', ['UAA', 'AUG', 'UGA', 'UAG'], 1],
            ['DNA nhân đôi theo nguyên tắc', ['Bổ sung và bán bảo toàn', 'Bảo toàn hoàn toàn', 'Phân tán', 'Ngẫu nhiên'], 0],
            ['Lai Aa × Aa (trội hoàn toàn) cho tỉ lệ kiểu hình', ['1 : 1', '1 : 2 : 1', '3 : 1', '9 : 3 : 3 : 1'], 2],
            ['Có bao nhiêu bộ ba mã di truyền?', ['20', '61', '64', '4'], 2],
            ['Đột biến làm dịch khung đọc là', ['Thay thế 1 cặp', 'Mất 1 cặp nucleotide', 'Đảo đoạn', 'Lặp đoạn NST'], 1],
        ],
    ],

    'Sinh học 11 trao đổi chất' => [
        'summary' => 'Trao đổi chất và chuyển hóa năng lượng ở thực vật và động vật – Sinh học 11 chương trình mới.',
        'objectives' => "Trình bày quá trình hấp thụ nước, khoáng và quang hợp ở thực vật.\nMô tả hô hấp, tuần hoàn ở động vật.\nLiên hệ kiến thức với dinh dưỡng và sức khỏe.",
        'description' => 'Khóa học kết nối kiến thức sinh lí với đời sống: vì sao cây cần bón đạm, vì sao vận động giúp tim khỏe.',
        'chapters' => [
            ['Chương 1. Trao đổi chất ở thực vật', [
                ['Hấp thụ nước và khoáng', '<p>Rễ cây hấp thụ nước chủ yếu qua tế bào lông hút theo cơ chế thẩm thấu và khoáng theo cơ chế chủ động hoặc thụ động. Nước được vận chuyển lên lá qua mạch gỗ nhờ lực hút do thoát hơi nước.</p>'],
                ['Quang hợp', '<p>Phương trình tổng quát: 6CO<sub>2</sub> + 6H<sub>2</sub>O → C<sub>6</sub>H<sub>12</sub>O<sub>6</sub> + 6O<sub>2</sub> (ánh sáng, diệp lục). Pha sáng diễn ra ở màng thylakoid, pha tối ở chất nền lục lạp.</p>'],
                ['Hô hấp ở thực vật', '<p>Hô hấp hiếu khí phân giải glucose thành CO<sub>2</sub>, H<sub>2</sub>O và giải phóng năng lượng ATP. Bảo quản nông sản bằng cách giảm nhiệt độ, giảm O<sub>2</sub> để hạn chế hô hấp.</p>'],
            ]],
            ['Chương 2. Trao đổi chất ở động vật', [
                ['Tiêu hóa', '<p>Ở người, thức ăn được tiêu hóa cơ học và hóa học; ruột non là nơi tiêu hóa và hấp thụ chủ yếu nhờ các nếp gấp, lông ruột làm tăng diện tích bề mặt.</p>'],
                ['Hô hấp ở động vật', '<p>Bề mặt trao đổi khí hiệu quả cần mỏng, ẩm, rộng và có nhiều mao mạch. Phổi người có khoảng 300 triệu phế nang với tổng diện tích khoảng 70 m<sup>2</sup>.</p>'],
                ['Tuần hoàn máu', '<p>Người có hệ tuần hoàn kín, kép: vòng tuần hoàn nhỏ qua phổi và vòng tuần hoàn lớn đi nuôi cơ thể. Tập thể dục đều đặn giúp tăng thể tích tâm thu và giảm nhịp tim lúc nghỉ.</p>'],
            ]],
        ],
        'questions' => [
            ['Pha tối của quang hợp diễn ra ở', ['Màng thylakoid', 'Chất nền lục lạp', 'Ti thể', 'Nhân tế bào'], 1],
            ['Nước được vận chuyển từ rễ lên lá qua', ['Mạch rây', 'Mạch gỗ', 'Tế bào biểu bì', 'Khí khổng'], 1],
            ['Nơi tiêu hóa và hấp thụ chủ yếu ở người là', ['Dạ dày', 'Ruột non', 'Ruột già', 'Thực quản'], 1],
            ['Hệ tuần hoàn của người là', ['Hở, đơn', 'Kín, đơn', 'Kín, kép', 'Hở, kép'], 2],
            ['Sản phẩm của quang hợp gồm', ['CO₂ và H₂O', 'Glucose và O₂', 'ATP và CO₂', 'O₂ và CO₂'], 1],
        ],
    ],

    'Hóa học vui cho học sinh' => [
        'summary' => 'Khám phá Hóa học qua các thí nghiệm an toàn tại nhà, dành cho học sinh THCS yêu thích khoa học.',
        'objectives' => "Làm quen dụng cụ và quy tắc an toàn phòng thí nghiệm.\nGiải thích hiện tượng hóa học trong đời sống.\nThực hiện 6 thí nghiệm đơn giản với vật liệu sẵn có.",
        'description' => 'Khóa học nhẹ nhàng, nhiều hình ảnh, giúp các em nhỏ thấy Hóa học gần gũi và thú vị.',
        'chapters' => [
            ['Chương 1. Làm quen với Hóa học', [
                ['Quy tắc an toàn', '<p>Luôn có người lớn bên cạnh khi làm thí nghiệm, đeo kính bảo hộ, không nếm hay ngửi trực tiếp hóa chất và rửa tay sạch sau khi làm xong.</p>'],
                ['Chất quanh ta', '<p>Muối ăn, đường, giấm, baking soda đều là các chất hóa học quen thuộc. Mỗi chất có tính chất riêng như màu, mùi, vị, khả năng tan trong nước.</p>'],
                ['Hiện tượng vật lí và hóa học', '<p>Nước đá tan là hiện tượng vật lí (không tạo chất mới). Đốt giấy hay sắt bị gỉ là hiện tượng hóa học vì tạo thành chất mới.</p>'],
            ]],
            ['Chương 2. Thí nghiệm tại nhà', [
                ['Núi lửa baking soda', '<p>Trộn baking soda (NaHCO<sub>3</sub>) với giấm (acetic acid) tạo khí CO<sub>2</sub> sủi bọt mạnh. Thêm vài giọt nước rửa chén và màu thực phẩm để có "dung nham".</p>'],
                ['Chỉ thị màu từ bắp cải tím', '<p>Nước ép bắp cải tím chuyển đỏ trong giấm (acid) và chuyển xanh lá trong nước xà phòng (base). Đây là chất chỉ thị tự nhiên giống quỳ tím.</p>'],
                ['Trứng nổi trong nước muối', '<p>Hòa tan nhiều muối làm khối lượng riêng của nước tăng, khi lớn hơn khối lượng riêng của trứng thì trứng sẽ nổi lên.</p>'],
            ]],
        ],
        'questions' => [
            ['Hiện tượng nào là hiện tượng hóa học?', ['Nước đá tan', 'Sắt bị gỉ', 'Muối tan trong nước', 'Cồn bay hơi'], 1],
            ['Baking soda trộn với giấm sinh ra khí', ['O₂', 'CO₂', 'H₂', 'N₂'], 1],
            ['Nước bắp cải tím trong giấm có màu', ['Đỏ', 'Xanh lá', 'Vàng', 'Không màu'], 0],
            ['Vì sao trứng nổi trong nước muối đặc?', ['Nước muối nóng hơn', 'Khối lượng riêng nước muối lớn hơn trứng', 'Trứng bị rỗng', 'Muối bám vào trứng'], 1],
            ['Khi làm thí nghiệm, điều nào KHÔNG nên làm?', ['Đeo kính bảo hộ', 'Nếm thử hóa chất', 'Rửa tay sau khi làm', 'Có người lớn bên cạnh'], 1],
        ],
    ],
];
