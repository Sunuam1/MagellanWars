#include "util.h"

bool 
CUnsignedIntegerList::free_item(TSomething aItem)
{
	return true;
}

CUnsignedIntegerList::~CUnsignedIntegerList()
{
	remove_all();
}

int 
CUnsignedIntegerList::compare(TSomething aItem1, TSomething aItem2) const
{
	return (uintptr_t)aItem1 - (uintptr_t)aItem2;
}

int 
CUnsignedIntegerList::compare_key(TSomething aItem, TConstSomething aKey) const
{
	return (uintptr_t)aItem - (uintptr_t)aKey;
}

CUnsignedIntegerList&
CUnsignedIntegerList::operator=(CUnsignedIntegerList& aList)
{
	remove_all();

	for(int i=0; i<aList.length(); i++)
		insert_sorted(aList.get(i));

	return *this;
}

CUnsignedIntegerList&
CUnsignedIntegerList::operator+=(CUnsignedIntegerList& aList)
{
	for(int i=0; i<aList.length(); i++)
		insert_sorted(aList.get(i));

	return *this;
}


