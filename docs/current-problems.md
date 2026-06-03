* Content creation wizard quá cơ bản, không đủ độ customizable
** Full SAT thì ổn rồi
** short test bị fixed time, và vẫn gen ra tận 6 module (not customizable).
** Custom lại cần choose test chứu không phải create ????

* Cần có cách để người dùng thay đổi data, ví dụ test duration module duration etc,..., cách tốt nhất là để dât trong table customizable.
* Trong test page, sau khi submit nó có bảo auto re-route, nhưng vẫn phải cần manually bấm nút, hãy route người dùng sau 5 giây.
* Test complete thì sẽ cần có cả option về home, chứ không phải mỗi go to result luôn, default là về home.
* Nếu treo ở màn hình re-routing khi test complete quá lâu, timer của test vẫn tiếp tục couunt down và khi hết thời gian đó nó lại submit lần nữa và màn hình time up lại hiện lên.
* id trong route mypractice để đi đến result cũng là một lỗ hổng bảo mật
* click vào logo ở bất cứ đâu nên đều về home
* implement take a break feature: dừng thời gian countdown nếu là test practice và bắt đầu lại khi người dùng quay lại và bấm continue test.
